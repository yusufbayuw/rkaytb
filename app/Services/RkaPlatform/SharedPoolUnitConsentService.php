<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\{Cycle, Membership, SharedPool, Unit};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SharedPoolUnitConsentService
{
    public function __construct(
        private TenantAccess $access,
        private AuditTrail $audit,
        private SharedCostAllocator $allocator,
    ) {}

    public function isRequired(Cycle $cycle): bool
    {
        // Older cycles retain their existing approval behavior; newly created
        // workspace cycles explicitly opt into full impacted-unit consent.
        return ($cycle->settings['sharing_unit_approval_policy'] ?? 'legacy') === 'required';
    }

    public function allocationHash(SharedPool $pool): string
    {
        $drivers = $pool->drivers ?? [];
        $allocations = $pool->allocations ?? [];
        ksort($drivers, SORT_NUMERIC);
        ksort($allocations, SORT_NUMERIC);

        return hash('sha256', json_encode([
            'organization_id' => $pool->organization_id,
            'cycle_id' => $pool->cycle_id,
            'pool_id' => $pool->id,
            'version' => (int)$pool->version,
            'amount' => (string)$pool->amount,
            'method' => $pool->method,
            'drivers' => $drivers,
            'allocations' => $allocations,
        ], JSON_THROW_ON_ERROR));
    }

    public function affectedUnitIds(SharedPool $pool): array
    {
        $expected = $this->allocator->preview($pool);
        $actual = $pool->allocations ?? [];
        if (!$actual || $expected !== $actual) {
            throw ValidationException::withMessages([
                'pool' => 'Alokasi tersimpan berbeda dengan perhitungan bobot; hitung ulang draft sebelum approval.',
            ]);
        }
        $ids = [];
        foreach ($actual as $unitId => $amount) {
            if ($this->allocator->toMinor((string)$amount) > 0) $ids[] = (int)$unitId;
        }
        if (!$ids) {
            throw ValidationException::withMessages(['pool' => 'Alokasi harus memiliki penerima dengan biaya positif.']);
        }
        sort($ids);
        return $ids;
    }

    public function sign(SharedPool $pool, int $actor): void
    {
        $member = $this->access->membership($pool->organization_id, $actor);
        if (!in_array('unit_manager', $member->roles ?? [], true) || $member->unit_id === null) {
            throw new AuthorizationException('Persetujuan Sharing Budget memerlukan penanggung jawab unit terdampak.');
        }

        DB::transaction(function () use ($pool, $actor, $member) {
            $locked = SharedPool::where('organization_id', $pool->organization_id)
                ->whereKey($pool->id)->lockForUpdate()->firstOrFail();
            $cycle = Cycle::where('organization_id', $pool->organization_id)
                ->whereKey($locked->cycle_id)->firstOrFail();
            if (!$this->isRequired($cycle) || $cycle->status !== 'draft' || $locked->status !== 'draft') {
                throw ValidationException::withMessages(['pool' => 'Persetujuan unit tidak diperlukan atau alokasi sudah dikunci.']);
            }
            if ((int)$locked->created_by === $actor) {
                throw new AuthorizationException('Pembuat alokasi tidak boleh menjadi penanda tangan unit.');
            }
            $unitId = (int)$member->unit_id;
            if (!Unit::where('organization_id', $locked->organization_id)->whereKey($unitId)->exists() ||
                !in_array($unitId, $this->affectedUnitIds($locked), true)) {
                throw new AuthorizationException('Unit bukan penerima biaya dari alokasi ini.');
            }
            if (DB::table('rka_sharing_disputes')->where('cycle_id', $locked->cycle_id)
                ->where('pool_id', $locked->id)->where('unit_id', $unitId)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['pool' => 'Selesaikan sengketa unit sebelum persetujuan.']);
            }
            $hash = $this->allocationHash($locked);
            $exists = DB::table('rka_shared_pool_unit_consents')
                ->where('pool_id', $locked->id)->where('unit_id', $unitId)
                ->where('pool_version', $locked->version)->exists();
            if ($exists) {
                throw ValidationException::withMessages(['pool' => 'Unit telah menyetujui versi alokasi ini.']);
            }
            $id = DB::table('rka_shared_pool_unit_consents')->insertGetId([
                'organization_id' => $locked->organization_id, 'cycle_id' => $locked->cycle_id,
                'pool_id' => $locked->id, 'unit_id' => $unitId,
                'pool_version' => $locked->version, 'allocation_hash' => $hash,
                'approved_by' => $actor, 'approved_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit->record($locked->organization_id, $actor,
                'shared_pool.unit_consented', 'pool_unit_consent', $id,
                ['pool_id' => $locked->id, 'unit_id' => $unitId, 'pool_version' => $locked->version, 'hash' => $hash]);
        });
    }

    public function requireAll(SharedPool $pool, Cycle $cycle): void
    {
        if ((int)$pool->organization_id !== (int)$cycle->organization_id ||
            (int)$pool->cycle_id !== (int)$cycle->id) {
            throw new AuthorizationException('Alokasi dan siklus tidak dalam organisasi yang sama.');
        }
        if (DB::table('rka_sharing_disputes')->where('organization_id', $cycle->organization_id)
            ->where('cycle_id', $cycle->id)->where('pool_id', $pool->id)
            ->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['pool' => 'Masih ada sengketa Sharing Budget terbuka.']);
        }
        if (!$this->isRequired($cycle)) {
            // Backwards compatibility: older approved pools did not retain drivers.
            // Still reject foreign recipients and incorrect allocation totals.
            $sum = 0;
            foreach ($pool->allocations ?? [] as $unitId => $amount) {
                if (!ctype_digit((string)$unitId) ||
                    !Unit::where('organization_id', $cycle->organization_id)->whereKey((int)$unitId)->exists()) {
                    throw ValidationException::withMessages(['pool' => 'Penerima biaya pada data lama tidak valid.']);
                }
                $sum += $this->allocator->toMinor((string)$amount);
            }
            if ($sum !== $this->allocator->toMinor((string)$pool->amount)) {
                throw ValidationException::withMessages(['pool' => 'Jumlah alokasi tidak sama dengan biaya pool.']);
            }
            return;
        }
        $ids = $this->affectedUnitIds($pool);

        $signed = DB::table('rka_shared_pool_unit_consents')
            ->where('organization_id', $pool->organization_id)
            ->where('cycle_id', $cycle->id)->where('pool_id', $pool->id)
            ->where('pool_version', $pool->version)
            ->where('allocation_hash', $this->allocationHash($pool))
            ->whereIn('unit_id', $ids)->pluck('unit_id')->map(fn ($x) => (int)$x)->all();
        $missing = array_diff($ids, $signed);
        if ($missing) {
            throw ValidationException::withMessages([
                'pool' => 'Persetujuan unit terdampak belum lengkap (unit ID: '.implode(', ', $missing).').',
            ]);
        }
    }
}
