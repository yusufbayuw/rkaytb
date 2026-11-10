<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\RkaPlatform\{Organization, Membership, Cycle, BudgetLine, SharedPool};
use App\Services\RkaPlatform\FinancialApprovalService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RkaPlatformFinancialApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_requires_independent_approver(): void
    {
        $org = Organization::create(['name' => 'Organization', 'slug' => 'org-fin']);
        $creator = User::factory()->create();
        $reviewer = User::factory()->create();
        foreach ([$creator, $reviewer] as $user) {
            Membership::create(['organization_id' => $org->id, 'user_id' => $user->id, 'roles' => ['finance']]);
        }
        $cycle = Cycle::create(['organization_id' => $org->id, 'name' => 'Cycle',
            'year' => 2031, 'starts_on' => '2031-01-01', 'ends_on' => '2031-12-31']);
        $budget = BudgetLine::create(['organization_id' => $org->id, 'cycle_id' => $cycle->id,
            'category' => 'cost', 'name' => 'General expense', 'amount' => '120.00',
            'created_by' => $creator->id]);
        $svc = app(FinancialApprovalService::class);
        try {
            $svc->approveBudget($budget, $creator->id);
            $this->fail('Self approval must be denied.');
        } catch (AuthorizationException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $svc->approveBudget($budget, $reviewer->id);
        $this->assertSame('approved', $budget->fresh()->status);

        $pool = SharedPool::create(['organization_id' => $org->id, 'cycle_id' => $cycle->id,
            'code' => 'POOL-1', 'name' => 'Shared expense', 'amount' => '50.00',
            'method' => 'equal', 'drivers' => ['1' => 1], 'allocations' => ['1' => '50.00'],
            'created_by' => $creator->id]);
        $svc->approvePool($pool, $reviewer->id);
        $this->assertSame('approved', $pool->fresh()->status);
    }
}
