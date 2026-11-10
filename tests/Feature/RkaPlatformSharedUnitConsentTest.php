<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\RkaPlatform\{Organization, Membership, Unit, Cycle, SharedPool};
use App\Services\RkaPlatform\{
    FinancialApprovalService, FinancialClosingService, FinancialReconciliationService,
    SharedCostAllocator, SharedPoolUnitConsentService, TenantAccess, AuditTrail
};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RkaPlatformSharedUnitConsentTest extends TestCase
{
    use RefreshDatabase;

    private function actor(Organization $org, array $roles, ?Unit $unit = null): User
    {
        $user = User::factory()->create();
        Membership::create([
            'organization_id' => $org->id, 'user_id' => $user->id,
            'roles' => $roles, 'unit_id' => $unit?->id, 'active' => true,
        ]);
        return $user;
    }

    private function fixture(): array
    {
        $org = Organization::create(['name' => 'Generic Services', 'slug' => 'generic-services']);
        $a = Unit::create(['organization_id' => $org->id, 'code' => 'A', 'name' => 'Unit Alpha']);
        $b = Unit::create(['organization_id' => $org->id, 'code' => 'B', 'name' => 'Unit Beta']);
        $cycle = Cycle::create([
            'organization_id' => $org->id, 'name' => 'Annual plan',
            'year' => 2040, 'starts_on' => '2040-01-01', 'ends_on' => '2040-12-31',
            'settings' => ['sharing_unit_approval_policy' => 'required'],
        ]);
        $maker = $this->actor($org, ['finance']);
        $checker = $this->actor($org, ['finance']);
        $headA = $this->actor($org, ['unit_manager'], $a);
        $headB = $this->actor($org, ['unit_manager'], $b);
        $pool = SharedPool::create([
            'organization_id' => $org->id, 'cycle_id' => $cycle->id,
            'code' => 'OVERHEAD', 'name' => 'Overhead', 'amount' => '101.00',
            'method' => 'weighted', 'drivers' => [(string)$a->id => 1, (string)$b->id => 2],
            'created_by' => $maker->id,
        ]);
        app(SharedCostAllocator::class)->saveDraft(
            $pool, $maker->id, app(TenantAccess::class), app(AuditTrail::class)
        );
        $pool->refresh();
        return compact('org','a','b','cycle','maker','checker','headA','headB','pool');
    }

    public function test_all_affected_units_must_sign_current_version_before_central_finance_approval(): void
    {
        $f = $this->fixture();
        $finance = app(FinancialApprovalService::class);
        $unit = app(SharedPoolUnitConsentService::class);
        try {
            $finance->approvePool($f['pool'], $f['checker']->id);
            $this->fail('Central reviewer cannot bypass impacted units.');
        } catch (ValidationException $expected) {
            $this->assertNotEmpty($expected->errors());
        }

        $unit->sign($f['pool'], $f['headA']->id);
        try {
            $finance->approvePool($f['pool'], $f['checker']->id);
            $this->fail('Second unit signature remains mandatory.');
        } catch (ValidationException $expected) {
            $this->assertNotEmpty($expected->errors());
        }
        $unit->sign($f['pool'], $f['headB']->id);
        $finance->approvePool($f['pool'], $f['checker']->id);
        $this->assertSame('approved', $f['pool']->fresh()->status);
        $this->assertSame(2, DB::table('rka_shared_pool_unit_consents')
            ->where('pool_id', $f['pool']->id)->count());
        $this->assertEqualsCanonicalizing([$f['a']->id, $f['b']->id],
            $unit->affectedUnitIds($f['pool']->fresh()));
    }

    public function test_expired_version_consent_is_rejected_when_pool_is_recalculated(): void
    {
        $f = $this->fixture();
        $unit = app(SharedPoolUnitConsentService::class);
        $unit->sign($f['pool'], $f['headA']->id);
        $unit->sign($f['pool'], $f['headB']->id);
        $original = $f['pool']->version;
        app(SharedCostAllocator::class)->saveDraft(
            $f['pool'], $f['maker']->id, app(TenantAccess::class), app(AuditTrail::class)
        );
        $this->assertGreaterThan($original, $f['pool']->fresh()->version);
        $this->expectException(ValidationException::class);
        app(FinancialApprovalService::class)->approvePool($f['pool'], $f['checker']->id);
    }

    public function test_outside_manager_cannot_sign_and_unrelated_unit_cannot_raise_a_dispute(): void
    {
        $f = $this->fixture();
        $external = Organization::create(['name' => 'Unrelated', 'slug' => 'unrelated']);
        $outsider = $this->actor($external, ['unit_manager']);

        try {
            app(SharedPoolUnitConsentService::class)->sign($f['pool'], $outsider->id);
            $this->fail('Other organization may not sign.');
        } catch (AuthorizationException $expected) {
            $this->assertNotEmpty($expected->getMessage());
        }

        $extra = Unit::create([
            'organization_id' => $f['org']->id, 'code' => 'C', 'name' => 'Unit Gamma',
        ]);
        $managerExtra = $this->actor($f['org'], ['unit_manager'], $extra);
        $this->expectException(ValidationException::class);
        app(FinancialClosingService::class)->raiseDispute(
            $f['cycle'], $managerExtra->id, $f['pool']->id, $extra->id,
            'This unit is not included in the pool but wants to block closing.'
        );
    }

    public function test_unit_signer_may_not_also_be_final_finance_reviewer(): void
    {
        $f = $this->fixture();
        $mixed = $this->actor($f['org'], ['finance','unit_manager'], $f['a']);
        $unit = app(SharedPoolUnitConsentService::class);
        $unit->sign($f['pool'], $mixed->id);
        $unit->sign($f['pool'], $f['headB']->id);
        $this->expectException(AuthorizationException::class);
        app(FinancialApprovalService::class)->approvePool($f['pool'], $mixed->id);
    }

    public function test_approved_pool_with_inconsistent_drivers_is_flagged_in_reconciliation(): void
    {
        $f = $this->fixture();
        $unit = app(SharedPoolUnitConsentService::class);
        $unit->sign($f['pool'], $f['headA']->id);
        $unit->sign($f['pool'], $f['headB']->id);
        app(FinancialApprovalService::class)->approvePool($f['pool'], $f['checker']->id);
        $f['pool']->update(['drivers' => [(string)$f['a']->id => 8, (string)$f['b']->id => 2]]);
        $codes = array_column(app(FinancialReconciliationService::class)
            ->calculate($f['cycle'])['report']['blockers'], 'code');
        $this->assertContains('POOL_ALLOCATION_OR_UNIT_CONSENT_INVALID', $codes);
    }
}
