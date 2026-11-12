<?php

namespace Tests\Feature;

use App\Filament\Pages\AccountProfile;
use App\Filament\Pages\RkaPlatformWorkspace;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FilamentUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->withoutVite();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('superadmin', 'web'));
        $this->actingAs($user);
        return $user;
    }

    public function test_every_registered_resource_and_custom_page_renders_on_empty_install(): void
    {
        $this->withoutExceptionHandling();
        $this->admin();
        $this->assertSame(0, Setting::count());
        $resources = Filament::getPanel('admin')->getResources();
        $this->assertCount(17, $resources);
        foreach ($resources as $resource) {
            $this->get($resource::getUrl('index', panel: 'admin'))->assertOk();
            $page = $resource::getPages()['index']->getPage();
            Livewire::test($page)->assertSuccessful();
        }
        $this->get('/admin')->assertOk();
        $this->get(AccountProfile::getUrl())->assertOk();
        $this->get(RkaPlatformWorkspace::getUrl())->assertOk();
        $this->assertSame(0, Setting::count(), 'Reading pages must not create settings.');
    }

    public function test_login_and_guest_download_redirect_use_the_new_panel_route(): void
    {
        $this->get('/admin/login')->assertOk();
        $this->get('/rka-platform/books/1/download/pdf')->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_install_command_creates_only_admin_and_technical_settings_and_is_idempotent(): void
    {
        $this->artisan('rka:install', ['--name' => 'Administrator', '--email' => 'admin@example.test'])
            ->expectsQuestion('Kata sandi administrator (minimal 12 karakter)', 'a-long-test-password')
            ->expectsQuestion('Ulangi kata sandi', 'a-long-test-password')->assertSuccessful();
        $admin = User::where('email', 'admin@example.test')->firstOrFail();
        $hash = $admin->password;
        $this->assertTrue($admin->hasRole('superadmin'));
        $this->assertSame(0, Unit::count());
        $this->assertSame(0, \App\Models\RkaPlatform\Organization::count());
        $this->assertFalse(Setting::flag(1));
        $this->assertFalse(Setting::flag(2));
        Setting::findOrFail(1)->update(['valbool' => true]);
        $this->artisan('rka:install', ['--name' => 'Other', '--email' => 'admin@example.test'])->assertSuccessful();
        $this->assertSame(1, User::count());
        $this->assertSame(2, Setting::count());
        $this->assertSame($hash, $admin->fresh()->password);
        $this->assertTrue(Setting::flag(1));
    }

    public function test_install_command_does_not_promote_existing_accounts(): void
    {
        $user = User::factory()->create();
        $this->artisan('rka:install', ['--name' => 'Other', '--email' => $user->email])->assertFailed();
        $this->assertFalse($user->fresh()->hasRole('superadmin'));
    }

    public function test_profile_changes_and_password_validation_use_current_livewire_actions(): void
    {
        $user = $this->admin();
        Livewire::test(AccountProfile::class)->set('name', 'Updated Administrator')->call('updateProfile')->assertHasNoErrors();
        $this->assertSame('Updated Administrator', $user->fresh()->name);
        Livewire::test(AccountProfile::class)->set('currentPassword', 'wrong')->set('newPassword', 'a-different-password')
            ->set('newPassword_confirmation', 'a-different-password')->call('changePassword')->assertHasErrors('currentPassword');
    }

    public function test_all_budget_widgets_render_for_admins_and_accounts_without_legacy_units(): void
    {
        $this->admin();
        $widgets = [
            \App\Filament\Resources\RkaResource\Widgets\TotalOverview::class,
            \App\Filament\Resources\RkaMaintenanceResource\Widgets\TotalMaintenanceOverview::class,
            \App\Filament\Resources\RkaConsumableResource\Widgets\TotalConsumableOverview::class,
            \App\Filament\Resources\RkaOperasionalResource\Widgets\TotalOperasionalOverview::class,
            \App\Filament\Resources\RkaPendapatanResource\Widgets\TotalPendapatanOverview::class,
            \App\Filament\Resources\RkaGajiResource\Widgets\TotalGajiOverview::class,
        ];
        foreach ($widgets as $widget) Livewire::withoutLazyLoading()->test($widget)->assertSee('Grand Total (Semua Unit)');
        $this->actingAs(User::factory()->create(['unit_id' => null]));
        foreach ($widgets as $widget) Livewire::withoutLazyLoading()->test($widget)->assertSee('Unit Belum Ditugaskan');
    }
}
