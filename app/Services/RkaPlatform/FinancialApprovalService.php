<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\{BudgetLine, SharedPool};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialApprovalService
{
    public function __construct(private TenantAccess $access, private AuditTrail $audit) {}

    public function approveBudget(BudgetLine $line, int $reviewerId): BudgetLine
    {
        $this->access->requireRole($line->organization_id, $reviewerId, ['owner','admin','finance']);
        return DB::transaction(function () use ($line, $reviewerId) {
            $locked = BudgetLine::query()->lockForUpdate()->findOrFail($line->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['budget' => 'Anggaran tidak berada dalam status draft.']);
            }
            if ($locked->created_by === $reviewerId) {
                throw new AuthorizationException('Penyusun anggaran tidak boleh menyetujui pekerjaannya sendiri.');
            }
            $locked->update(['status' => 'approved', 'approved_by' => $reviewerId, 'approved_at' => now()]);
            $this->audit->record($locked->organization_id, $reviewerId, 'budget.approved', 'budget_line', $locked->id);
            return $locked;
        });
    }

    public function approvePool(SharedPool $pool, int $reviewerId): SharedPool
    {
        $this->access->requireRole($pool->organization_id, $reviewerId, ['owner','admin','finance']);
        return DB::transaction(function () use ($pool, $reviewerId) {
            $locked = SharedPool::query()->lockForUpdate()->findOrFail($pool->id);
            if ($locked->status !== 'draft' || empty($locked->allocations)) {
                throw ValidationException::withMessages(['pool' => 'Alokasi belum lengkap atau tidak lagi draft.']);
            }
            if ($locked->created_by === $reviewerId) {
                throw new AuthorizationException('Penyusun alokasi tidak boleh menyetujui hasilnya sendiri.');
            }
            $locked->update(['status' => 'approved', 'approved_by' => $reviewerId, 'approved_at' => now()]);
            $this->audit->record($locked->organization_id, $reviewerId, 'shared_pool.approved', 'shared_pool', $locked->id);
            return $locked;
        });
    }
}
