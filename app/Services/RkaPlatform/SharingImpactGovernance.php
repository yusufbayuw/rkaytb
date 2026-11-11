<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\{Cycle, SharedPool};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SharingImpactGovernance
{
    public function __construct(
        private TenantAccess $access,
        private AuditTrail $audit,
        private SharedPoolUnitConsentService $units
    ) {}

    private function fail(string $reason): never
    {
        throw ValidationException::withMessages(['impact' => $reason]);
    }

    public function forPool(SharedPool $pool): ?object
    {
        return DB::table('rka_shared_pool_impact_assessments')
            ->where('organization_id', $pool->organization_id)
            ->where('cycle_id', $pool->cycle_id)->where('pool_id', $pool->id)
            ->where('pool_version', $pool->version)
            ->where('allocation_hash', $this->units->allocationHash($pool))
            ->first();
    }

    private function previousApprovedBaseline(SharedPool $pool, Cycle $cycle): ?SharedPool
    {
        return SharedPool::query()->join('rka_cycles as historical_cycle',
                'historical_cycle.id', '=', 'rka_shared_pools.cycle_id')
            ->where('rka_shared_pools.organization_id', $pool->organization_id)
            ->where('rka_shared_pools.code', $pool->code)
            ->where('rka_shared_pools.status', 'approved')
            ->where('historical_cycle.status', 'closed')
            ->whereDate('historical_cycle.ends_on', '<', $cycle->starts_on->format('Y-m-d'))
            ->orderByDesc('historical_cycle.ends_on')
            ->select('rka_shared_pools.*')->first();
    }

    private function recipientIds(SharedPool $pool): array
    {
        $ids = [];
        foreach ($pool->allocations ?? [] as $id => $amount) {
            if ($this->units->allocationHash($pool) && (float)$amount > 0) $ids[] = (int)$id;
        }
        sort($ids);
        return $ids;
    }

    private function normalizedDrivers(SharedPool $pool): array
    {
        $items = $pool->drivers ?? [];
        ksort($items, SORT_NUMERIC);
        return array_map(fn ($value) => (string)(float)$value, $items);
    }

    public function approved(SharedPool $pool): object
    {
        $assessment = $this->forPool($pool);
        if (!$assessment || $assessment->status !== 'approved' || !$assessment->reviewed_by ||
            $assessment->prepared_by === $assessment->reviewed_by) {
            $this->fail('Penilaian dampak independen untuk versi alokasi ini belum disahkan.');
        }
        if ($assessment->change_type !== 'baseline') {
            $cycle = Cycle::where('organization_id',$pool->organization_id)
                ->whereKey($pool->cycle_id)->firstOrFail();
            $baseline = $this->previousApprovedBaseline($pool, $cycle);
            if (!$baseline || (int)$baseline->id !== (int)$assessment->baseline_pool_id ||
                $this->units->allocationHash($baseline) !== $assessment->baseline_hash) {
                $this->fail('Baseline sumber untuk klasifikasi dampak berubah atau tidak lagi sah.');
            }
        }
        return $assessment;
    }

    public function propose(SharedPool $pool, int $actor, string $changeType,
        string $tier, string $reason, ?string $dueAt = null): int
    {
        $this->access->requireRole($pool->organization_id, $actor, ['owner','admin','finance']);
        $allowed = [
            'baseline' => ['approve'],
            'routine' => ['inform','review','approve'],
            'parameters' => ['review','approve'],
            'transfer' => ['approve'],
        ];
        if (!isset($allowed[$changeType]) || !in_array($tier, $allowed[$changeType], true) ||
            mb_strlen(trim($reason)) < 20) {
            $this->fail('Jenis perubahan, respons unit, atau alasan tidak valid. Baseline dan perpindahan tanggung jawab wajib approval unit.');
        }
        if ($tier === 'review' && (!$dueAt || !strtotime($dueAt) || Carbon::parse($dueAt)->lte(now()))) {
            $this->fail('Review unit wajib memiliki tenggat di masa depan untuk jalur eskalasi.');
        }
        if ($tier !== 'review' && $dueAt !== null) $this->fail('Tenggat eskalasi hanya berlaku untuk tier review.');

        return DB::transaction(function () use ($pool,$actor,$changeType,$tier,$reason,$dueAt) {
            $locked = SharedPool::where('organization_id',$pool->organization_id)
                ->whereKey($pool->id)->lockForUpdate()->firstOrFail();
            $cycle = Cycle::where('organization_id',$locked->organization_id)
                ->whereKey($locked->cycle_id)->lockForUpdate()->firstOrFail();
            if (($cycle->settings['sharing_unit_approval_policy'] ?? '') !== 'impact_based' ||
                $cycle->status !== 'draft' || $locked->status !== 'draft') {
                $this->fail('Penilaian dampak hanya berlaku bagi alokasi draft pada siklus impact-based.');
            }
            // Invalid recipients, amounts or weights cannot be hidden behind a risk label.
            $this->units->affectedUnitIds($locked);
            $baseline = $this->previousApprovedBaseline($locked, $cycle);
            if ($changeType !== 'baseline' && !$baseline) {
                $this->fail('Tidak ada baseline pool dari siklus terdahulu yang ditutup; klasifikasi wajib baseline dengan persetujuan semua unit.');
            }
            if ($baseline && $changeType === 'routine') {
                if ($baseline->method !== $locked->method ||
                    $this->normalizedDrivers($baseline) !== $this->normalizedDrivers($locked) ||
                    $this->recipientIds($baseline) !== $this->recipientIds($locked)) {
                    $this->fail('Rutin hanya berlaku ketika metode, bobot, dan penerima sama dengan baseline resmi.');
                }
            }
            if ($baseline && $changeType === 'parameters' &&
                $this->recipientIds($baseline) !== $this->recipientIds($locked)) {
                $this->fail('Perubahan unit penerima adalah pemindahan tanggung jawab dan wajib approval formal.');
            }
            if (DB::table('rka_shared_pool_impact_assessments')->where('pool_id',$locked->id)
                ->where('pool_version',$locked->version)->exists()) {
                $this->fail('Penilaian dampak untuk versi ini sudah diajukan; hitung ulang alokasi untuk revisi.');
            }
            $id = DB::table('rka_shared_pool_impact_assessments')->insertGetId([
                'organization_id' => $locked->organization_id, 'cycle_id' => $locked->cycle_id,
                'pool_id' => $locked->id, 'pool_version' => $locked->version,
                'allocation_hash' => $this->units->allocationHash($locked),
                'baseline_pool_id' => $baseline?->id,
                'baseline_hash' => $baseline ? $this->units->allocationHash($baseline) : null,
                'change_type' => $changeType, 'review_tier' => $tier, 'reason' => trim($reason),
                'response_due_at' => $dueAt ? Carbon::parse($dueAt) : null,
                'prepared_by' => $actor, 'status' => 'draft',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit->record($locked->organization_id, $actor, 'sharing.impact_proposed',
                'pool_impact_assessment', $id, ['pool_version'=>$locked->version,'type'=>$changeType,'tier'=>$tier]);
            return $id;
        });
    }

    public function approveAssessment(SharedPool $pool, int $assessmentId, int $actor): void
    {
        $this->access->requireRole($pool->organization_id, $actor, ['owner','admin']);
        DB::transaction(function () use ($pool,$assessmentId,$actor) {
            $locked = SharedPool::where('organization_id',$pool->organization_id)
                ->whereKey($pool->id)->lockForUpdate()->firstOrFail();
            $cycle = Cycle::where('organization_id',$locked->organization_id)
                ->whereKey($locked->cycle_id)->firstOrFail();
            $row = DB::table('rka_shared_pool_impact_assessments')
                ->where('organization_id',$locked->organization_id)
                ->where('cycle_id',$locked->cycle_id)->where('pool_id',$locked->id)
                ->where('id',$assessmentId)->lockForUpdate()->first();
            if (!$row) abort(404);
            if ($cycle->status !== 'draft' || $locked->status !== 'draft' ||
                $row->pool_version !== $locked->version ||
                $row->allocation_hash !== $this->units->allocationHash($locked) ||
                $row->status !== 'draft') {
                $this->fail('Versi penilaian dampak sudah tidak cocok dengan alokasi aktif.');
            }
            if ($row->prepared_by === $actor || (int)$locked->created_by === $actor) {
                throw new AuthorizationException('Penilai independen tidak boleh menjadi pembuat alokasi atau pengusul klasifikasi.');
            }
            $this->units->affectedUnitIds($locked);
            if ($row->change_type !== 'baseline') {
                $baseline = $this->previousApprovedBaseline($locked, $cycle);
                if (!$baseline || (int)$baseline->id !== (int)$row->baseline_pool_id ||
                    $this->units->allocationHash($baseline) !== $row->baseline_hash) {
                    $this->fail('Baseline berubah; wajib lakukan penilaian ulang.');
                }
            }
            DB::table('rka_shared_pool_impact_assessments')->where('id',$row->id)->update([
                'status'=>'approved','reviewed_by'=>$actor,'reviewed_at'=>now(),'updated_at'=>now(),
            ]);
            $this->audit->record($locked->organization_id,$actor,'sharing.impact_approved',
                'pool_impact_assessment',$row->id,['tier'=>$row->review_tier]);
        });
    }

    public function proposeEscalation(SharedPool $pool, int $unitId, int $actor, string $reason): int
    {
        $this->access->requireRole($pool->organization_id,$actor,['owner','admin']);
        if (mb_strlen(trim($reason)) < 20) $this->fail('Alasan eskalasi minimal 20 karakter.');
        return DB::transaction(function () use ($pool,$unitId,$actor,$reason) {
            $locked = SharedPool::where('organization_id',$pool->organization_id)
                ->whereKey($pool->id)->lockForUpdate()->firstOrFail();
            $cycle = Cycle::where('organization_id',$locked->organization_id)
                ->whereKey($locked->cycle_id)->firstOrFail();
            if ($cycle->status !== 'draft' || $locked->status !== 'draft' ||
                (int)$locked->created_by === $actor) {
                $this->fail('Pembuat alokasi tidak dapat mengeskalasi sendiri atau alokasi sudah dikunci.');
            }
            $assessment = $this->approved($locked);
            if ($assessment->review_tier !== 'review' || !$assessment->response_due_at ||
                Carbon::parse($assessment->response_due_at)->isFuture()) {
                $this->fail('Eskalasi hanya untuk review yang telah melewati tenggat; tidak ada persetujuan otomatis.');
            }
            if (!in_array($unitId, $this->units->affectedUnitIds($locked), true)) {
                $this->fail('Unit tidak terdampak alokasi.');
            }
            if ($this->isSigned($locked, $unitId) ||
                DB::table('rka_sharing_disputes')->where('organization_id',$locked->organization_id)
                    ->where('cycle_id',$locked->cycle_id)->where('pool_id',$locked->id)
                    ->where('unit_id',$unitId)->where('status','open')->exists()) {
                $this->fail('Unit sudah merespons atau memiliki sengketa yang belum diputuskan.');
            }
            if (DB::table('rka_shared_pool_escalations')->where('pool_id',$locked->id)
                ->where('unit_id',$unitId)->where('pool_version',$locked->version)->exists()) {
                $this->fail('Unit sudah memiliki eskalasi untuk versi ini.');
            }
            $id = DB::table('rka_shared_pool_escalations')->insertGetId([
                'organization_id'=>$locked->organization_id,'cycle_id'=>$locked->cycle_id,
                'pool_id'=>$locked->id,'unit_id'=>$unitId,'pool_version'=>$locked->version,
                'allocation_hash'=>$this->units->allocationHash($locked),
                'reason'=>trim($reason),'status'=>'pending','proposed_by'=>$actor,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->audit->record($locked->organization_id,$actor,'sharing.review_escalation_proposed',
                'pool_escalation',$id,['unit_id'=>$unitId]);
            return $id;
        });
    }

    public function approveEscalation(SharedPool $pool, int $escalationId, int $actor): void
    {
        $this->access->requireRole($pool->organization_id,$actor,['owner','admin']);
        DB::transaction(function () use ($pool,$escalationId,$actor) {
            $locked = SharedPool::where('organization_id',$pool->organization_id)
                ->whereKey($pool->id)->lockForUpdate()->firstOrFail();
            $cycle = Cycle::where('organization_id',$locked->organization_id)
                ->whereKey($locked->cycle_id)->firstOrFail();
            $row = DB::table('rka_shared_pool_escalations')->where('organization_id',$locked->organization_id)
                ->where('cycle_id',$locked->cycle_id)->where('pool_id',$locked->id)
                ->where('id',$escalationId)->lockForUpdate()->first();
            if (!$row) abort(404);
            if ($cycle->status !== 'draft' || $locked->status !== 'draft' ||
                $row->status !== 'pending' || $row->pool_version !== $locked->version ||
                $row->allocation_hash !== $this->units->allocationHash($locked) ||
                $this->approved($locked)->review_tier !== 'review') {
                $this->fail('Eskalasi atau versi data tidak lagi valid.');
            }
            if ($row->proposed_by === $actor || (int)$locked->created_by === $actor) {
                throw new AuthorizationException('Dua pejabat berbeda wajib menilai eskalasi; pembuat alokasi dilarang mengesahkan.');
            }
            if ($this->isSigned($locked, (int)$row->unit_id) ||
                DB::table('rka_sharing_disputes')->where('organization_id',$locked->organization_id)
                    ->where('cycle_id',$locked->cycle_id)->where('pool_id',$locked->id)
                    ->where('unit_id',$row->unit_id)->where('status','open')->exists()) {
                $this->fail('Tidak boleh mengeskalasi respons atau sengketa unit yang masih terbuka.');
            }
            DB::table('rka_shared_pool_escalations')->where('id',$row->id)->update([
                'status'=>'approved','approved_by'=>$actor,'approved_at'=>now(),'updated_at'=>now(),
            ]);
            $this->audit->record($locked->organization_id,$actor,'sharing.review_escalation_approved',
                'pool_escalation',$row->id,['unit_id'=>$row->unit_id]);
        });
    }

    public function isSigned(SharedPool $pool, int $unitId): bool
    {
        return DB::table('rka_shared_pool_unit_consents')->where('organization_id',$pool->organization_id)
            ->where('cycle_id',$pool->cycle_id)->where('pool_id',$pool->id)
            ->where('unit_id',$unitId)->where('pool_version',$pool->version)
            ->where('allocation_hash',$this->units->allocationHash($pool))->exists();
    }

    public function approvedEscalatedUnits(SharedPool $pool): array
    {
        return DB::table('rka_shared_pool_escalations')->where('organization_id',$pool->organization_id)
            ->where('cycle_id',$pool->cycle_id)->where('pool_id',$pool->id)
            ->where('pool_version',$pool->version)->where('allocation_hash',$this->units->allocationHash($pool))
            ->where('status','approved')->pluck('unit_id')->map(fn ($id) => (int)$id)->all();
    }
}
