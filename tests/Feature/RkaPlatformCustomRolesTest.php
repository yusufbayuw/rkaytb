<?php

namespace Tests\Feature;

use App\Filament\Pages\RkaPlatformWorkspace;
use App\Models\User;
use App\Models\RkaPlatform\{Organization, Membership, Cycle, ApprovalRequest};
use App\Services\RkaPlatform\{OrganizationRoleCatalog, FinancialClosingService};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RkaPlatformCustomRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_approval_roles_are_configurable_without_global_role_grants(): void
    {
        $org = Organization::create(['name' => 'Generic institution', 'slug' => 'generic-institution']);
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $analyst = User::factory()->create();
        $visitor = User::factory()->create();
        foreach ([[$owner,['owner']],[$admin,['admin']],[$analyst,['supervisor_budget']],[$visitor,['contributor']]] as [$user,$roles]) {
            Membership::create(['organization_id' => $org->id, 'user_id' => $user->id, 'roles' => $roles]);
        }
        $catalog = app(OrganizationRoleCatalog::class);
        $catalog->add($org->id, $owner->id, 'supervisor_budget', 'Budget Supervisor');
        $catalog->validateAssignable($org->id, 'supervisor_budget');
        $this->assertSame('Budget Supervisor', $catalog->roles($org->id)['supervisor_budget']);
        $this->assertFalse($analyst->hasRole('superadmin'));

        $closing = app(FinancialClosingService::class);
        $policy = $closing->proposePolicy($org->id, $owner->id, 'cycle.close', [
            ['label' => 'Supervisor', 'roles' => ['supervisor_budget']],
            ['label' => 'Organization Head', 'roles' => ['owner']],
        ]);
        $closing->activatePolicy($policy, $admin->id);
        $this->assertTrue($policy->fresh()->active);

        $cycle = Cycle::create(['organization_id' => $org->id, 'name' => 'Cycle',
            'year' => 2039, 'starts_on' => '2039-01-01', 'ends_on' => '2039-12-31']);
        ApprovalRequest::create([
            'organization_id' => $org->id, 'action' => 'cycle.close',
            'subject_type' => 'cycle', 'subject_id' => $cycle->id, 'requested_by' => $owner->id,
            'steps_snapshot' => ['steps' => $policy->steps, 'policy_id' => $policy->id],
            'risk' => 'normal', 'status' => 'pending',
        ]);
        Livewire::actingAs($analyst)->test(RkaPlatformWorkspace::class)
            ->assertSee('Closing, Pembukaan Kembali dan Persetujuan')
            ->assertSee('Setujui');
        Livewire::actingAs($visitor)->test(RkaPlatformWorkspace::class)
            ->assertDontSee('Closing, Pembukaan Kembali dan Persetujuan');
    }

    public function test_reserved_owner_role_cannot_be_assigned_or_redefined(): void
    {
        $org = Organization::create(['name' => 'Other', 'slug' => 'other']);
        $owner = User::factory()->create();
        Membership::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'roles' => ['owner']]);
        $this->expectException(AuthorizationException::class);
        app(OrganizationRoleCatalog::class)->validateAssignable($org->id, 'owner');
    }
}
