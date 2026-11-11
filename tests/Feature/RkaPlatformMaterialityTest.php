<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\RkaPlatform\{Organization, Membership, Cycle, BudgetLine};
use App\Services\RkaPlatform\{MaterialityExceptionService, FinancialReconciliationService};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RkaPlatformMaterialityTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = Organization::create(['name'=>'Flexible Organization','slug'=>'flexible-organization']);
        $cycle = Cycle::create([
            'organization_id'=>$org->id,'name'=>'Budget Cycle','year'=>2037,
            'starts_on'=>'2037-01-01','ends_on'=>'2037-12-31',
            'settings'=>['kpi_profile'=>[
                'income_categories'=>['REV'],'expense_categories'=>['COST'],
                'sharing_already_in_expense'=>false,
            ]],
        ]);
        $users = [];
        foreach (['owner','admin','finance'] as $role) {
            $user = User::factory()->create();
            Membership::create([
                'organization_id'=>$org->id,'user_id'=>$user->id,'roles'=>[$role],
            ]);
            $users[$role] = $user;
        }
        BudgetLine::create([
            'organization_id'=>$org->id,'cycle_id'=>$cycle->id,
            'category'=>'REV','name'=>'Income','amount'=>'500.00','status'=>'approved',
        ]);
        BudgetLine::create([
            'organization_id'=>$org->id,'cycle_id'=>$cycle->id,
            'category'=>'COST','name'=>'Expense','amount'=>'100.00','status'=>'approved',
        ]);
        $unknown = BudgetLine::create([
            'organization_id'=>$org->id,'cycle_id'=>$cycle->id,
            'category'=>'UNKNOWN','name'=>'Ambiguous old category','amount'=>'1.00',
            'status'=>'approved',
        ]);
        return compact('org','cycle','users','unknown');
    }

    public function test_budget_exception_requires_two_people_and_two_materiality_caps(): void
    {
        $f = $this->fixture();
        $recon = app(FinancialReconciliationService::class);
        $before = array_column($recon->calculate($f['cycle'])['report']['blockers'],'code');
        $this->assertContains('UNMAPPED_BUDGET_CATEGORY',$before);
        $svc = app(MaterialityExceptionService::class);
        $policyId = $svc->propose($f['cycle'],$f['users']['owner']->id,'2.00',100);
        try {
            $svc->activate($f['cycle'],$policyId,$f['users']['owner']->id);
            $this->fail('Policy self activation must be blocked.');
        } catch (AuthorizationException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $svc->activate($f['cycle'],$policyId,$f['users']['admin']->id);
        $caseId = $svc->request($f['cycle'],$f['unknown']->id,$f['users']['finance']->id,
            'Historical uncategorized line, accepted pending normal mapping');
        try {
            $svc->approve($f['cycle'],$caseId,$f['users']['finance']->id);
            $this->fail('Requestor cannot approve own exception.');
        } catch (AuthorizationException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $svc->approve($f['cycle'],$caseId,$f['users']['admin']->id);
        $after = $recon->calculate($f['cycle'])['report'];
        $this->assertNotContains('UNMAPPED_BUDGET_CATEGORY',array_column($after['blockers'],'code'));
        $this->assertSame(1,$after['approved_exception_count']);
        $this->assertSame(100,$after['excluded_budget_minor']);

        $second = BudgetLine::create([
            'organization_id'=>$f['org']->id,'cycle_id'=>$f['cycle']->id,
            'category'=>'UNMATCHED','name'=>'Second uncategorized line','amount'=>'1.50',
            'status'=>'approved',
        ]);
        $case2 = $svc->request($f['cycle'],$second->id,$f['users']['finance']->id,
            'Second ambiguous transaction from an old chart of accounts');
        $this->expectException(ValidationException::class);
        $svc->approve($f['cycle'],$case2,$f['users']['admin']->id);
    }

    public function test_other_organization_members_cannot_activate_materiality_policy(): void
    {
        $f = $this->fixture();
        $other = Organization::create(['name'=>'Other','slug'=>'unrelated']);
        $outsider = User::factory()->create();
        Membership::create(['organization_id'=>$other->id,'user_id'=>$outsider->id,
            'roles'=>['owner']]);
        $policyId = app(MaterialityExceptionService::class)->propose(
            $f['cycle'],$f['users']['owner']->id,'5.00',100
        );
        $this->expectException(AuthorizationException::class);
        app(MaterialityExceptionService::class)->activate($f['cycle'],$policyId,$outsider->id);
    }
}
