<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\RkaPlatform\{Organization, Membership};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RkaPlatformPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unassigned_account_is_not_allowed_into_filament(): void
    {
        $user = User::factory()->create(['unit_id' => null]);
        $this->assertFalse($user->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));

        $org = Organization::create(['name' => 'Example Entity', 'slug' => 'example-entity']);
        $member = Membership::create([
            'organization_id' => $org->id, 'user_id' => $user->id,
            'roles' => ['contributor'], 'active' => true,
        ]);
        $this->assertTrue($user->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));
        $member->update(['active' => false]);
        $this->assertFalse($user->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));
    }

    public function test_existing_global_role_or_unit_assignment_keeps_panel_access(): void
    {
        $user = User::factory()->create(['unit_id' => null]);
        $role = Role::findOrCreate('finance_officer', 'web');
        $user->assignRole($role);
        $this->assertTrue($user->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));

        $legacyUser = User::factory()->create(['unit_id' => null]);
        $legacyUser->unit_id = 123;
        $this->assertTrue($legacyUser->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));
    }
}
