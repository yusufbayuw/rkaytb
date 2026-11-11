<?php

namespace Tests\Feature;

use App\Filament\Pages\RkaPlatformWorkspace;
use App\Filament\Pages\AccountProfile;
use App\Models\User;
use App\Models\RkaPlatform\{Organization, Membership, Cycle};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RkaPlatformWorkspaceSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_member_can_render_workspace_without_organization_seed(): void
    {
        $user = User::factory()->create();
        $org = Organization::create([
            'name' => 'Generic Test Cooperative', 'slug' => 'generic-test-cooperative',
            'currency' => 'USD', 'locale' => 'en', 'timezone' => 'UTC',
        ]);
        Membership::create([
            'organization_id' => $org->id, 'user_id' => $user->id,
            'roles' => ['owner'], 'active' => true,
        ]);
        Cycle::create([
            'organization_id' => $org->id, 'name' => 'Budget Planning',
            'year' => 2034, 'starts_on' => '2034-01-01', 'ends_on' => '2034-12-31',
        ]);

        Livewire::actingAs($user)->test(RkaPlatformWorkspace::class)
            ->assertSee('Generic Test Cooperative')
            ->assertSee('Budget Planning')
            ->assertSee('USD');
    }

    public function test_builtin_profile_page_renders_for_signed_in_users(): void
    {
        $user = User::factory()->create(['name' => 'Generic User']);
        Livewire::actingAs($user)->test(AccountProfile::class)
            ->assertSet('name', 'Generic User')
            ->assertSee('Ganti Kata Sandi');
    }
}
