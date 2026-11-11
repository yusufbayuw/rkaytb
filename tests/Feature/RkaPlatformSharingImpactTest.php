<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\RkaPlatform\{Organization, Membership, Unit, Cycle, SharedPool};
use App\Services\RkaPlatform\{
    SharingImpactGovernance, SharedPoolUnitConsentService,
    SharedCostAllocator, FinancialApprovalService, TenantAccess, AuditTrail
};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RkaPlatformSharingImpactTest extends TestCase
{
    use RefreshDatabase;

    private function member(Organization $org, array $roles, ?Unit $unit = null): User
    {
        $user = User::factory()->create();
        Membership::create(['organization_id'=>$org->id,'user_id'=>$user->id,
            'roles'=>$roles,'unit_id'=>$unit?->id,'active'=>true]);
        return $user;
    }

    private function fixture(): array
    {
        $org = Organization::create(['name'=>'Independent Holding','slug'=>'independent-holding']);
        $a = Unit::create(['organization_id'=>$org->id,'code'=>'A','name'=>'Division A']);
        $b = Unit::create(['organization_id'=>$org->id,'code'=>'B','name'=>'Division B']);
        $cycle = Cycle::create([
            'organization_id'=>$org->id,'name'=>'Budget Year','year'=>2041,
            'starts_on'=>'2041-01-01','ends_on'=>'2041-12-31',
            'settings'=>['sharing_unit_approval_policy'=>'impact_based'],
        ]);
        $maker = $this->member($org,['finance']);
        $checker = $this->member($org,['finance']);
        $owner = $this->member($org,['owner']);
        $admin = $this->member($org,['admin']);
        $headA = $this->member($org,['unit_manager'],$a);
        $headB = $this->member($org,['unit_manager'],$b);
        $pool = SharedPool::create([
            'organization_id'=>$org->id,'cycle_id'=>$cycle->id,
            'code'=>'OPS','name'=>'Central operating cost','amount'=>'120.00',
            'method'=>'weighted','drivers'=>[(string)$a->id=>1,(string)$b->id=>2],
            'created_by'=>$maker->id,
        ]);
        app(SharedCostAllocator::class)->saveDraft(
            $pool,$maker->id,app(TenantAccess::class),app(AuditTrail::class)
        );
        $pool->refresh();
        // A closed earlier-year, independently approved pool is the verified
        // baseline for routine/parameter changes in the impact-based workflow.
        $prior = Cycle::create([
            'organization_id'=>$org->id,'name'=>'Prior Budget Year','year'=>2034,
            'starts_on'=>'2034-01-01','ends_on'=>'2034-12-31','status'=>'closed',
        ]);
        SharedPool::create([
            'organization_id'=>$org->id,'cycle_id'=>$prior->id,
            'code'=>$pool->code,'name'=>'Prior central operating cost',
            'amount'=>$pool->amount,'method'=>$pool->method,
            'drivers'=>$pool->drivers,'allocations'=>$pool->allocations,
            'version'=>1,'status'=>'approved','created_by'=>$maker->id,
            'approved_by'=>$checker->id,'approved_at'=>now(),
        ]);
        return compact('org','a','b','cycle','maker','checker','owner','admin','headA','headB','pool');
    }

    public function test_routine_information_tier_requires_independent_review_but_not_unit_signatures(): void
    {
        $f = $this->fixture();
        $govern = app(SharingImpactGovernance::class);
        try {
            app(FinancialApprovalService::class)->approvePool($f['pool'],$f['checker']->id);
            $this->fail('Impact classification is mandatory');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        $id = $govern->propose($f['pool'],$f['maker']->id,'routine','inform',
            'A routine allocation under the existing approved operational formula.');
        try {
            $govern->approveAssessment($f['pool'],$id,$f['maker']->id);
            $this->fail('Maker cannot approve impact policy.');
        } catch (AuthorizationException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $govern->approveAssessment($f['pool'],$id,$f['owner']->id);
        app(FinancialApprovalService::class)->approvePool($f['pool'],$f['checker']->id);
        $this->assertSame('approved',$f['pool']->fresh()->status);
        $this->assertSame(0,DB::table('rka_shared_pool_unit_consents')->count());
    }

    public function test_baseline_and_responsibility_transfer_cannot_be_classified_as_information_only(): void
    {
        $f = $this->fixture();
        $g = app(SharingImpactGovernance::class);
        foreach ([['baseline','inform'],['transfer','review'],['parameters','inform']] as [$type,$tier]) {
            try {
                $g->propose($f['pool'],$f['maker']->id,$type,$tier,
                    'Classification attempted with inadequate affected-unit involvement.');
                $this->fail($type.' should not allow '.$tier);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $assessment = $g->propose($f['pool'],$f['maker']->id,'baseline','approve',
            'First budget sharing baseline requires both recipient units to formally approve.');
        $g->approveAssessment($f['pool'],$assessment,$f['owner']->id);
        $units = app(SharedPoolUnitConsentService::class);
        $units->sign($f['pool'],$f['headA']->id);
        $units->sign($f['pool'],$f['headB']->id);
        app(FinancialApprovalService::class)->approvePool($f['pool'],$f['checker']->id);
        $this->assertSame('approved',$f['pool']->fresh()->status);
    }

    public function test_review_may_escalate_after_deadline_only_with_two_distinct_officers(): void
    {
        Carbon::setTestNow('2035-02-01 08:00:00');
        try {
            $f = $this->fixture();
            $g = app(SharingImpactGovernance::class);
            $assessment = $g->propose($f['pool'],$f['maker']->id,'parameters','review',
                'Approved finance parameter revision with a limited unit operational impact.',
                '2035-02-02 12:00:00');
            $g->approveAssessment($f['pool'],$assessment,$f['owner']->id);
            app(SharedPoolUnitConsentService::class)->sign($f['pool'],$f['headA']->id);
            try {
                $g->proposeEscalation($f['pool'],$f['b']->id,$f['owner']->id,
                    'No acknowledgement yet, but the deadline has not been reached.');
                $this->fail('Escalation before deadline must be blocked.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
            Carbon::setTestNow('2035-02-03 09:00:00');
            $case = $g->proposeEscalation($f['pool'],$f['b']->id,$f['owner']->id,
                'Review window passed after documented reminders and no formal objection.');
            try {
                $g->approveEscalation($f['pool'],$case,$f['owner']->id);
                $this->fail('Self approval of an escalation is forbidden.');
            } catch (AuthorizationException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
            try {
                app(FinancialApprovalService::class)->approvePool($f['pool'],$f['checker']->id);
                $this->fail('Pending escalation is not sufficient.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
            $g->approveEscalation($f['pool'],$case,$f['admin']->id);
            app(FinancialApprovalService::class)->approvePool($f['pool'],$f['checker']->id);
            $this->assertSame('approved',$f['pool']->fresh()->status);
            $this->assertSame('approved',DB::table('rka_shared_pool_escalations')->find($case)->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_stale_assessment_after_recalculation_cannot_authorize_financial_approval(): void
    {
        $f = $this->fixture();
        $g = app(SharingImpactGovernance::class);
        $assessment = $g->propose($f['pool'],$f['maker']->id,'routine','inform',
            'Routine allocation classified and independently reviewed at original version.');
        $g->approveAssessment($f['pool'],$assessment,$f['owner']->id);
        app(SharedCostAllocator::class)->saveDraft(
            $f['pool'],$f['maker']->id,app(TenantAccess::class),app(AuditTrail::class)
        );
        $this->expectException(ValidationException::class);
        app(FinancialApprovalService::class)->approvePool($f['pool'],$f['checker']->id);
    }

    public function test_outside_owner_cannot_approve_another_organization_impact_assessment(): void
    {
        $f = $this->fixture();
        $assessment = app(SharingImpactGovernance::class)->propose(
            $f['pool'],$f['maker']->id,'routine','inform',
            'Normal operating charge under an established organizational policy.');
        $other = Organization::create(['name'=>'Other','slug'=>'other']);
        $outsider = $this->member($other,['owner']);
        $this->expectException(AuthorizationException::class);
        app(SharingImpactGovernance::class)->approveAssessment($f['pool'],$assessment,$outsider->id);
    }
}
