<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\{BudgetLine, Cycle};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterialityExceptionService
{
    public function __construct(
        private TenantAccess $access,
        private AuditTrail $audit,
        private SharedCostAllocator $money
    ) {}

    private function fail(string $reason): never
    {
        throw ValidationException::withMessages(['materiality' => $reason]);
    }

    public function propose(Cycle $cycle, int $actor, string $absoluteAmount, int $percentageBps): int
    {
        $this->access->requireRole($cycle->organization_id, $actor, ['owner','admin']);
        if ($cycle->status !== 'draft') $this->fail('Siklus sudah ditutup.');
        if ($percentageBps < 1 || $percentageBps > 10000) $this->fail('Persentase materialitas harus 1–10.000 basis poin.');
        $amount = $this->money->toMinor($absoluteAmount);
        if ($amount <= 0) $this->fail('Batas nominal materialitas harus positif.');
        return DB::transaction(function () use ($cycle,$actor,$amount,$percentageBps) {
            $version = (int)DB::table('rka_materiality_policies')->where('cycle_id',$cycle->id)->max('version') + 1;
            $id = DB::table('rka_materiality_policies')->insertGetId([
                'organization_id'=>$cycle->organization_id, 'cycle_id'=>$cycle->id,
                'version'=>$version, 'absolute_minor'=>$amount,
                'percentage_bps'=>$percentageBps, 'status'=>'draft',
                'created_by'=>$actor, 'created_at'=>now(), 'updated_at'=>now(),
            ]);
            $this->audit->record($cycle->organization_id,$actor,'materiality.proposed','materiality_policy',$id);
            return $id;
        });
    }

    public function activate(Cycle $cycle, int $policyId, int $actor): void
    {
        $this->access->requireRole($cycle->organization_id,$actor,['owner','admin']);
        DB::transaction(function () use ($cycle,$policyId,$actor) {
            $locked = Cycle::where('organization_id',$cycle->organization_id)
                ->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'draft') $this->fail('Siklus sudah ditutup.');
            $policy = DB::table('rka_materiality_policies')->where('organization_id',$cycle->organization_id)
                ->where('cycle_id',$cycle->id)->where('id',$policyId)->lockForUpdate()->first();
            if (!$policy) abort(404);
            if ($policy->status !== 'draft') $this->fail('Hanya draft kebijakan yang dapat diaktifkan.');
            if ($policy->created_by === $actor) {
                throw new AuthorizationException('Penyusun materialitas tidak boleh menyetujui kebijakannya sendiri.');
            }
            DB::table('rka_materiality_policies')->where('cycle_id',$cycle->id)
                ->where('status','active')->update(['status'=>'superseded', 'updated_at'=>now()]);
            DB::table('rka_materiality_policies')->where('id',$policyId)->update([
                'status'=>'active','approved_by'=>$actor,'approved_at'=>now(),'updated_at'=>now(),
            ]);
            $this->audit->record($cycle->organization_id,$actor,'materiality.activated','materiality_policy',$policyId);
        });
    }

    public function request(Cycle $cycle, int $budgetId, int $actor, string $reason): int
    {
        $this->access->requireRole($cycle->organization_id,$actor,['owner','admin','finance']);
        if ($cycle->status !== 'draft') $this->fail('Siklus sudah ditutup.');
        if (mb_strlen(trim($reason)) < 10) $this->fail('Alasan pengecualian minimal 10 karakter.');
        $budget = BudgetLine::where('organization_id',$cycle->organization_id)->where('cycle_id',$cycle->id)
            ->where('status','approved')->findOrFail($budgetId);
        $profile = ($cycle->settings ?? [])['kpi_profile'] ?? null;
        if (!is_array($profile) ||
            in_array($budget->category, array_merge($profile['income_categories'] ?? [],
                $profile['expense_categories'] ?? []),true)) {
            $this->fail('Pengecualian hanya untuk kategori anggaran yang belum dipetakan ke KPI.');
        }
        $amount = $this->money->toMinor((string)$budget->amount);
        if ($amount <= 0) $this->fail('Pengecualian hanya untuk nilai positif.');
        if (DB::table('rka_close_exceptions')->where('cycle_id',$cycle->id)
            ->where('budget_line_id',$budget->id)->whereIn('status',['requested','approved'])->exists()) {
            $this->fail('Sudah ada pengecualian aktif pada baris anggaran ini.');
        }
        $id = DB::table('rka_close_exceptions')->insertGetId([
            'organization_id'=>$cycle->organization_id,'cycle_id'=>$cycle->id,
            'budget_line_id'=>$budget->id,'code'=>'UNMAPPED_BUDGET_CATEGORY',
            'amount_minor'=>$amount,'reason'=>trim($reason),
            'status'=>'requested','requested_by'=>$actor,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->audit->record($cycle->organization_id,$actor,'close_exception.requested','close_exception',$id);
        return $id;
    }

    public function approve(Cycle $cycle, int $exceptionId, int $actor): void
    {
        $this->access->requireRole($cycle->organization_id,$actor,['owner','admin','finance']);
        DB::transaction(function () use ($cycle,$exceptionId,$actor) {
            $locked = Cycle::where('organization_id',$cycle->organization_id)
                ->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'draft') $this->fail('Siklus sudah ditutup.');
            $exception = DB::table('rka_close_exceptions')->where('organization_id',$cycle->organization_id)
                ->where('cycle_id',$cycle->id)->where('id',$exceptionId)->lockForUpdate()->first();
            if (!$exception) abort(404);
            if ($exception->status !== 'requested') $this->fail('Pengecualian bukan permintaan aktif.');
            if ($exception->requested_by === $actor) {
                throw new AuthorizationException('Pengaju tidak boleh menyetujui pengecualian sendiri.');
            }
            $policy = DB::table('rka_materiality_policies')
                ->where('organization_id',$cycle->organization_id)->where('cycle_id',$cycle->id)
                ->where('status','active')->first();
            if (!$policy) $this->fail('Kebijakan materialitas belum disahkan.');
            $budget = BudgetLine::where('organization_id',$cycle->organization_id)
                ->where('cycle_id',$cycle->id)->where('status','approved')
                ->findOrFail($exception->budget_line_id);
            $profile = ($locked->settings ?? [])['kpi_profile'] ?? null;
            if (!is_array($profile) || in_array($budget->category,
                array_merge($profile['income_categories'] ?? [],$profile['expense_categories'] ?? []),true)) {
                $this->fail('Kategori bukan lagi kategori yang dikecualikan.');
            }
            if ($this->money->toMinor((string)$budget->amount) !== (int)$exception->amount_minor) {
                $this->fail('Nominal baris anggaran berubah setelah pengajuan.');
            }
            $total = 0;
            foreach (BudgetLine::where('organization_id',$cycle->organization_id)
                ->where('cycle_id',$cycle->id)->where('status','approved')->get(['amount']) as $budgetLine) {
                $total += $this->money->toMinor((string)$budgetLine->amount);
            }
            $percentLimit = intdiv($total,10000) * (int)$policy->percentage_bps +
                intdiv(($total % 10000) * (int)$policy->percentage_bps,10000);
            $limit = min((int)$policy->absolute_minor, $percentLimit);
            $existing = (int)DB::table('rka_close_exceptions')->where('cycle_id',$cycle->id)
                ->where('status','approved')->sum('amount_minor');
            if ($existing + (int)$exception->amount_minor > $limit) {
                $this->fail('Pengecualian kumulatif melampaui batas nominal atau persentase yang disahkan.');
            }
            DB::table('rka_close_exceptions')->where('id',$exception->id)->update([
                'status'=>'approved','policy_id'=>$policy->id,
                'approved_by'=>$actor,'approved_at'=>now(),'updated_at'=>now(),
            ]);
            $this->audit->record($cycle->organization_id,$actor,'close_exception.approved','close_exception',$exception->id,
                ['policy_id'=>$policy->id,'amount_minor'=>$exception->amount_minor]);
        });
    }
}
