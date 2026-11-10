<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\{SharedPool, Unit};
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class SharedCostAllocator
{
    public function toMinor(string $amount): int
    {
        if (!preg_match('/^\d{1,15}(?:\.\d{1,2})?$/', $amount)) {
            throw ValidationException::withMessages(['amount' => 'Nominal harus positif dan maksimal dua desimal.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        return (int)$whole * 100 + (int)str_pad($fraction, 2, '0');
    }

    public function fromMinor(int $amount): string
    {
        return intdiv($amount, 100) . '.' . str_pad((string)($amount % 100), 2, '0', STR_PAD_LEFT);
    }

    // Driver keys are unit IDs; values are nonnegative numeric weights. This does NOT post journals.
    public function calculate(string $amount, array $drivers): array
    {
        $totalMinor = $this->toMinor($amount);
        if ($drivers === []) {
            throw ValidationException::withMessages(['drivers' => 'Minimal satu penerima wajib dipilih.']);
        }
        $weights = [];
        foreach ($drivers as $id => $value) {
            if (!ctype_digit((string)$id) || (int)$id < 1 ||
                !is_numeric($value) || !is_finite((float)$value) || (float)$value < 0) {
                throw ValidationException::withMessages(['drivers' => 'Bobot penerima tidak valid.']);
            }
            $weights[(int)$id] = (float)$value;
        }
        ksort($weights, SORT_NUMERIC);
        $sum = array_sum($weights);
        if ($sum <= 0) throw ValidationException::withMessages(['drivers' => 'Total bobot harus positif.']);
        $shares = [];
        $remainders = [];
        $distributed = 0;
        foreach ($weights as $id => $weight) {
            $exact = $totalMinor * ($weight / $sum);
            $base = (int)floor($exact);
            $shares[$id] = $base;
            $remainders[$id] = $exact - $base;
            $distributed += $base;
        }
        $order = array_keys($weights);
        usort($order, fn ($a, $b) => ($remainders[$b] <=> $remainders[$a]) ?: ($a <=> $b));
        for ($i = 0; $i < $totalMinor - $distributed; $i++) $shares[$order[$i % count($order)]]++;
        ksort($shares, SORT_NUMERIC);
        return array_map(fn ($v) => $this->fromMinor($v), $shares);
    }

    public function preview(SharedPool $pool): array
    {
        $drivers = $pool->drivers ?? [];
        if ($pool->method === 'equal') {
            $drivers = array_fill_keys(array_keys($drivers), 1);
        } elseif ($pool->method !== 'weighted') {
            throw ValidationException::withMessages(['method' => 'Metode belum dikenali.']);
        }
        if (Unit::query()->where('organization_id', $pool->organization_id)->whereIn('id', array_keys($drivers))->count() !== count($drivers)) {
            throw ValidationException::withMessages(['drivers' => 'Penerima bukan milik organisasi.']);
        }
        return $this->calculate((string)$pool->amount, $drivers);
    }

    public function saveDraft(SharedPool $pool, int $userId, TenantAccess $access, AuditTrail $audit): array
    {
        $access->requireRole($pool->organization_id, $userId, ['owner','admin','finance']);
        if ($pool->status !== 'draft') throw ValidationException::withMessages(['pool' => 'Alokasi yang sudah disahkan tidak dapat ditimpa.']);
        $results = $this->preview($pool);
        DB::transaction(function () use ($pool, $results, $userId, $audit) {
            $pool->update(['allocations' => $results, 'version' => $pool->version + 1]);
            $audit->record($pool->organization_id, $userId, 'shared_pool.calculated', 'shared_pool', $pool->id, ['version' => $pool->version]);
        });
        return $results;
    }
}
