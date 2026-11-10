<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\{
    ApprovalPolicy, ApprovalRequest, ApprovalDecision, Cycle, SharedPool, Unit
};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialClosingService
{
    public function __construct(
        private TenantAccess $access,
        private AuditTrail $audit,
        private FinancialReconciliationService $reconciliation
    ) {}

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['closing' => $message]);
    }

    private function checkSteps(array $steps): void
    {
        if (!array_is_list($steps) || count($steps) < 2 || count($steps) > 5) {
            $this->fail('Kebijakan wajib memiliki 2-5 tahap approval berjenjang.');
        }
        foreach ($steps as $step) {
            if (!is_array($step) || !isset($step['roles']) || !is_array($step['roles']) ||
                !array_is_list($step['roles']) || !$step['roles'] || count($step['roles']) > 10) {
                $this->fail('Setiap tahap wajib mempunyai minimal satu role.');
            }
            foreach ($step['roles'] as $role) {
                if (!is_string($role) || !preg_match('/^[a-z][a-z0-9_-]{1,49}$/', $role)) {
                    $this->fail('Format nama role tidak valid.');
                }
            }
            if (array_diff(array_keys($step), ['roles','label'])) {
                $this->fail('Field policy tidak dikenali.');
            }
        }
    }

    public function proposePolicy(int $orgId, int $actor, string $action, array $steps,
        string $risk = 'normal'): ApprovalPolicy
    {
        $this->access->requireRole($orgId, $actor, ['owner','admin']);
        if (!in_array($action, ['cycle.close','cycle.reopen'], true) ||
            !in_array($risk, ['normal','high'], true)) $this->fail('Action atau tingkat risiko tidak tersedia.');
        $this->checkSteps($steps);
        return DB::transaction(function () use ($orgId, $actor, $action, $steps, $risk) {
            $version = ApprovalPolicy::where('organization_id', $orgId)->where('action', $action)->max('version') + 1;
            $policy = ApprovalPolicy::create([
                'organization_id' => $orgId, 'action' => $action,
                'conditions' => ['risk' => $risk], 'steps' => $steps, 'active' => false,
                'priority' => 100, 'version' => $version, 'created_by' => $actor,
            ]);
            $this->audit->record($orgId, $actor, 'approval_policy.proposed', 'approval_policy', $policy->id,
                ['version' => $version]);
            return $policy;
        });
    }

    public function activatePolicy(ApprovalPolicy $policy, int $reviewer): void
    {
        $this->access->requireRole($policy->organization_id, $reviewer, ['owner','admin']);
        DB::transaction(function () use ($policy, $reviewer) {
            $locked = ApprovalPolicy::where('organization_id', $policy->organization_id)
                ->whereKey($policy->id)->lockForUpdate()->firstOrFail();
            if ($locked->created_by === $reviewer) {
                throw new AuthorizationException('Pembuat kebijakan tidak boleh mengaktifkan kebijakannya sendiri.');
            }
            if ($locked->active) $this->fail('Kebijakan sudah aktif.');
            $this->checkSteps($locked->steps ?? []);
            app(OrganizationRoleCatalog::class)->validateApprovalRoles($locked->organization_id, $locked->steps);
            $risk = ($locked->conditions ?? [])['risk'] ?? 'normal';
            foreach (ApprovalPolicy::where('organization_id', $locked->organization_id)
                ->where('action', $locked->action)->where('active', true)->get() as $other) {
                if (($other->conditions['risk'] ?? 'normal') === $risk) {
                    $other->update(['active' => false]);
                }
            }
            $locked->update(['active' => true, 'activated_by' => $reviewer, 'activated_at' => now()]);
            $this->audit->record($locked->organization_id, $reviewer, 'approval_policy.activated',
                'approval_policy', $locked->id);
        });
    }

    private function selectPolicy(Cycle $cycle, string $action, string $risk): ApprovalPolicy
    {
        $policy = ApprovalPolicy::where('organization_id', $cycle->organization_id)
            ->where('action', $action)->where('active', true)
            ->whereNotNull('activated_by')->orderBy('priority')->orderByDesc('version')->get()
            ->first(fn ($p) => ($p->conditions['risk'] ?? 'normal') === $risk);
        if (!$policy) $this->fail('Belum ada kebijakan approval aktif untuk action dan tingkat risiko ini.');
        $this->checkSteps($policy->steps ?? []);
        return $policy;
    }

    private function latestVerified(Cycle $cycle): object
    {
        $run = DB::table('rka_reconciliation_runs')->where('organization_id', $cycle->organization_id)
            ->where('cycle_id', $cycle->id)->orderByDesc('version')->first();
        if (!$run || $run->status !== 'verified' || !$run->verified_by ||
            $run->prepared_by === $run->verified_by) {
            $this->fail('Rekonsiliasi terbaru harus disahkan pemeriksa independen.');
        }
        $computed = $this->reconciliation->calculate($cycle);
        $report = json_decode($run->report, true, 512, JSON_THROW_ON_ERROR);
        if ($computed['source_hash'] !== $run->source_hash ||
            $computed['report']['blocker_count'] !== 0 || ($report['blocker_count'] ?? 1) !== 0) {
            $this->fail('Sumber berubah atau blocker belum tuntas. Lakukan rekonsiliasi ulang.');
        }
        return $run;
    }

    public function request(Cycle $cycle, int $actor, string $action, string $reason): ApprovalRequest
    {
        $roles = $action === 'cycle.reopen' ? ['owner','admin'] : ['owner','admin','finance'];
        $this->access->requireRole($cycle->organization_id, $actor, $roles);
        if (!in_array($action, ['cycle.close','cycle.reopen'], true)) $this->fail('Aksi closing tidak dikenal.');
        if (mb_strlen(trim($reason)) < 10) $this->fail('Alasan pengajuan minimal 10 karakter.');
        return DB::transaction(function () use ($cycle, $actor, $action, $reason) {
            $locked = Cycle::where('organization_id', $cycle->organization_id)
                ->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $needsStatus = $action === 'cycle.close' ? 'draft' : 'closed';
            if ($locked->status !== $needsStatus) $this->fail('Status siklus tidak sesuai untuk permintaan ini.');
            if (ApprovalRequest::where('organization_id', $locked->organization_id)
                ->where('subject_type', 'cycle')->where('subject_id', $locked->id)
                ->where('status', 'pending')->exists()) {
                $this->fail('Sudah ada permintaan approval yang belum selesai.');
            }
            $risk = $action === 'cycle.reopen' ? 'high' : (($locked->settings ?? [])['close_risk'] ?? 'normal');
            $policy = $this->selectPolicy($locked, $action, $risk);
            $run = $action === 'cycle.close' ? $this->latestVerified($locked) : null;
            if ($action === 'cycle.reopen' && !DB::table('rka_cycle_closings')
                ->where('cycle_id', $locked->id)->exists()) $this->fail('Belum ada snapshot closing untuk dibuka ulang.');
            $req = ApprovalRequest::create([
                'organization_id' => $locked->organization_id, 'action' => $action,
                'subject_type' => 'cycle', 'subject_id' => $locked->id,
                'requested_by' => $actor, 'status' => 'pending', 'risk' => $risk,
                'steps_snapshot' => ['policy_id' => $policy->id, 'policy_version' => $policy->version,
                    'steps' => $policy->steps],
                'current_step' => 0, 'reason' => trim($reason),
                'source_hash' => $run?->source_hash,
                'reconciliation_id' => $run?->id,
            ]);
            $this->audit->record($locked->organization_id, $actor, 'cycle_approval.requested',
                'approval_request', $req->id, ['action' => $action, 'policy_id' => $policy->id]);
            return $req;
        });
    }

    public function decide(ApprovalRequest $request, int $actor, bool $approved, string $reason = ''): void
    {
        $this->access->membership($request->organization_id, $actor);
        DB::transaction(function () use ($request, $actor, $approved, $reason) {
            $req = ApprovalRequest::where('organization_id', $request->organization_id)
                ->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($req->status !== 'pending' || $req->subject_type !== 'cycle' ||
                !in_array($req->action, ['cycle.close','cycle.reopen'], true)) {
                $this->fail('Permintaan sudah selesai atau tidak didukung.');
            }
            $cycle = Cycle::where('organization_id', $req->organization_id)
                ->whereKey($req->subject_id)->lockForUpdate()->firstOrFail();
            $steps = $req->steps_snapshot['steps'] ?? [];
            $step = $req->current_step;
            if (!isset($steps[$step])) $this->fail('Tahap approval tidak valid.');
            if ($req->requested_by === $actor ||
                ApprovalDecision::where('request_id', $req->id)->where('actor_id', $actor)->exists()) {
                throw new AuthorizationException('Approval wajib independen di setiap tahap.');
            }
            $this->access->requireRole($req->organization_id, $actor, $steps[$step]['roles']);
            if (!$approved && mb_strlen(trim($reason)) < 10) $this->fail('Penolakan wajib disertai alasan minimal 10 karakter.');
            if ($req->action === 'cycle.close') {
                if ($cycle->status !== 'draft') $this->fail('Siklus tidak lagi terbuka.');
                $run = $this->latestVerified($cycle);
                if ($req->reconciliation_id !== $run->id || $req->source_hash !== $run->source_hash) {
                    $this->fail('Rekonsiliasi berubah sejak pengajuan closing.');
                }
            } elseif ($cycle->status !== 'closed') $this->fail('Siklus tidak sedang ditutup.');

            ApprovalDecision::create([
                'request_id' => $req->id, 'actor_id' => $actor, 'step' => $step,
                'decision' => $approved ? 'approved' : 'rejected', 'reason' => trim($reason) ?: null,
            ]);
            if (!$approved) {
                $req->update(['status' => 'rejected']);
            } elseif ($step + 1 < count($steps)) {
                $req->update(['current_step' => $step + 1]);
            } else {
                $req->update(['current_step' => $step + 1, 'status' => 'approved']);
                if ($req->action === 'cycle.close') $this->finalize($cycle, $req, $actor);
                else $cycle->update(['status' => 'draft']);
            }
            $this->audit->record($req->organization_id, $actor, 'cycle_approval.decided',
                'approval_request', $req->id, ['action' => $req->action, 'step' => $step, 'approved' => $approved]);
        });
    }

    private function finalize(Cycle $cycle, ApprovalRequest $req, int $actor): void
    {
        $run = $this->latestVerified($cycle);
        if ($run->id !== $req->reconciliation_id || $run->source_hash !== $req->source_hash) {
            $this->fail('Rekonsiliasi berubah sebelum closing final.');
        }
        $report = json_decode($run->report, true, 512, JSON_THROW_ON_ERROR);
        $version = (int) DB::table('rka_cycle_closings')->where('cycle_id', $cycle->id)->max('version') + 1;
        $hash = hash('sha256', json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $closingId = DB::table('rka_cycle_closings')->insertGetId([
            'organization_id' => $cycle->organization_id, 'cycle_id' => $cycle->id,
            'reconciliation_id' => $run->id, 'approval_request_id' => $req->id,
            'version' => $version, 'source_hash' => $run->source_hash,
            'report_hash' => $hash, 'report' => json_encode($report, JSON_THROW_ON_ERROR),
            'closed_by' => $actor, 'closed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $cycle->update(['status' => 'closed']);
        $this->audit->record($cycle->organization_id, $actor, 'cycle.closed', 'cycle', $cycle->id,
            ['closing_id' => $closingId, 'version' => $version, 'hash' => $hash]);
    }

    public function raiseDispute(Cycle $cycle, int $actor, int $poolId, int $unitId, string $reason): int
    {
        $membership = $this->access->membership($cycle->organization_id, $actor);
        if ($cycle->status !== 'draft') $this->fail('Sengketa hanya dapat diajukan sebelum closing.');
        if (mb_strlen(trim($reason)) < 10) $this->fail('Alasan sengketa minimal 10 karakter.');
        $pool = SharedPool::where('organization_id', $cycle->organization_id)
            ->where('cycle_id', $cycle->id)->findOrFail($poolId);
        $unit = Unit::where('organization_id', $cycle->organization_id)->findOrFail($unitId);
        if (!$this->access->hasRole($cycle->organization_id, $actor, ['owner','admin','finance']) &&
            ((int)$membership->unit_id !== $unitId ||
                !count(array_intersect($membership->roles ?? [], ['unit_manager','contributor'])))) {
            throw new AuthorizationException('Hanya unit terdampak dapat menyampaikan sengketa.');
        }
        $id = DB::table('rka_sharing_disputes')->insertGetId([
            'organization_id' => $cycle->organization_id, 'cycle_id' => $cycle->id,
            'pool_id' => $pool->id, 'unit_id' => $unit->id,
            'reason' => trim($reason), 'status' => 'open', 'raised_by' => $actor,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit->record($cycle->organization_id, $actor, 'sharing.dispute_raised', 'sharing_dispute', $id);
        return $id;
    }

    public function resolveDispute(Cycle $cycle, int $disputeId, int $actor, string $resolution): void
    {
        $this->access->requireRole($cycle->organization_id, $actor, ['owner','admin','finance']);
        if (mb_strlen(trim($resolution)) < 10) $this->fail('Resolusi sengketa minimal 10 karakter.');
        DB::transaction(function () use ($cycle, $disputeId, $actor, $resolution) {
            $locked = Cycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'draft') $this->fail('Periode telah ditutup.');
            $d = DB::table('rka_sharing_disputes')->where('organization_id', $cycle->organization_id)
                ->where('cycle_id', $cycle->id)->where('id', $disputeId)->lockForUpdate()->first();
            if (!$d) abort(404);
            if ($d->raised_by === $actor) {
                throw new AuthorizationException('Pengaju sengketa tidak boleh menyelesaikan sengketanya sendiri.');
            }
            if ($d->status !== 'open') $this->fail('Sengketa sudah diputuskan.');
            DB::table('rka_sharing_disputes')->where('id', $d->id)->update([
                'status' => 'resolved', 'resolution' => trim($resolution),
                'resolved_by' => $actor, 'resolved_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit->record($cycle->organization_id, $actor, 'sharing.dispute_resolved', 'sharing_dispute', $d->id);
        });
    }
}
