<?php

namespace Tests\Feature;

use App\Filament\Pages\{SystemHealth, SystemLogs};
use App\Filament\Resources\{RoleResource, SystemActivityResource, UnitResource};
use App\Models\{Rka, Unit, User, Role};
use App\Services\AccessManagement;
use App\Support\StandardPermissions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OperationalPluginsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->withoutVite();
        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::findOrCreate('superadmin', 'web'));
        $this->actingAs($this->admin);
    }

    public function test_permission_sync_is_idempotent_and_preserves_existing_ids_and_assignments(): void
    {
        $permission = Permission::create(['name' => 'Keep:Custom', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'custom', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $this->artisan('rka:sync-permissions', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(1, Permission::count());
        $this->artisan('rka:sync-permissions')->assertSuccessful();
        $ids = Permission::orderBy('id')->pluck('id', 'name')->all();
        $this->artisan('rka:sync-permissions')->assertSuccessful();
        $this->assertSame($ids, Permission::orderBy('id')->pluck('id', 'name')->all());
        $this->assertSame(25, Permission::count());
        $this->assertSame([$permission->id], $role->permissions()->pluck('permissions.id')->all());
        $this->assertFalse(Role::whereIn('name', ['super_admin', 'panel_user'])->exists());
        $resources = \BezhanSalleh\FilamentShield\Facades\FilamentShield::getResources();
        $this->assertCount(6, $resources);
        $this->assertSame('read:user', StandardPermissions::key(\App\Filament\Resources\UserResource::class, 'viewAny', '', '', ''));
    }

    public function test_shield_create_and_edit_preserve_custom_permissions_and_reject_foreign_guards(): void
    {
        StandardPermissions::sync();
        Permission::create(['name' => 'Keep:Custom', 'guard_name' => 'web']);
        Livewire::test(RoleResource::getPages()['create']->getPage())->fillForm([
            'name' => 'review-role', 'guard_name' => 'web', UnitResource::class => ['read:unit'],
            'extra_permissions' => ['Keep:Custom'],
        ])->call('create')->assertHasNoFormErrors();
        $role = Role::findByName('review-role', 'web');
        $this->assertTrue($role->hasPermissionTo('read:unit'));
        $this->assertTrue($role->hasPermissionTo('Keep:Custom'));
        Livewire::test(RoleResource::getPages()['edit']->getPage(), ['record' => $role->id])
            ->fillForm(['name' => 'review-role-updated'])->call('save')->assertHasNoFormErrors();
        $this->assertTrue($role->fresh()->hasPermissionTo('Keep:Custom'));
        $other = Role::create(['name' => 'other-guard', 'guard_name' => 'api']);
        $this->get(RoleResource::getUrl('edit', ['record' => $other]))->assertNotFound();
    }

    public function test_system_pages_and_role_mutations_require_superadmin_even_with_global_permissions(): void
    {
        StandardPermissions::sync();
        $unit = Unit::create(['nama' => 'Unit']);
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $user->givePermissionTo(['read:role', 'create:role', 'update:role', 'read:permission', 'create:permission']);
        $this->actingAs($user);
        foreach (['/admin/system/activities', '/admin/system/health', '/admin/system/logs', '/admin/roles/create'] as $url) $this->get($url)->assertForbidden();
        $this->get('/admin/roles')->assertOk();
        $this->assertFalse(RoleResource::canCreate());
        $this->assertFalse(\App\Filament\Resources\PermissionResource::canCreate());
        Livewire::test(SystemHealth::class)->assertForbidden();
        try {
            AccessManagement::syncRoles($user, [Role::findByName('superadmin')->id]);
            $this->fail('Non-admin role escalation accepted');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $this->assertFalse($user->fresh()->hasRole('superadmin'));
    }

    public function test_revoked_superadmin_cannot_use_an_already_open_system_component(): void
    {
        $this->admin->update(['unit_id' => Unit::create(['nama' => 'Unit'])->id]);
        $component = Livewire::test(SystemHealth::class)->assertSuccessful();
        $this->admin->roles()->detach();
        $component->call('refresh')->assertForbidden();
    }

    public function test_reserved_role_and_last_administrator_are_protected(): void
    {
        $role = Role::findByName('superadmin');
        foreach ([fn () => $role->update(['name' => 'renamed']), fn () => $role->fresh()->delete(),
            fn () => AccessManagement::syncRoles($this->admin, []), fn () => AccessManagement::deleteUser($this->admin)] as $action) {
            try { $action(); $this->fail('Protected administrator was modified'); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
        $this->assertTrue($this->admin->fresh()->hasRole('superadmin'));
        $second = User::factory()->create();
        $second->assignRole('superadmin');
        AccessManagement::syncRoles($second, []);
        $this->assertFalse($second->fresh()->hasRole('superadmin'));
    }

    public function test_audit_records_single_changes_and_rolls_back_with_business_transactions(): void
    {
        Activity::query()->delete();
        $row = Rka::create(['uraian' => 'Audit budget', 'unit_nama' => '1', 'is_parent' => false, 'volume' => 2, 'harga_satuan' => 12.5]);
        $this->assertSame(1, Activity::where('subject_type', Rka::class)->where('subject_id', $row->id)->where('event', 'Created')->count());
        $row->update(['volume' => 3]);
        $this->assertSame(1, Activity::where('subject_type', Rka::class)->where('subject_id', $row->id)->where('event', 'Updated')->count());
        $this->assertEquals(37.5, $row->fresh()->total);
        $count = Activity::count();
        try { DB::transaction(function () { Unit::create(['nama' => 'ROLLBACK']); throw new \RuntimeException('rollback'); }); }
        catch (\RuntimeException) {}
        $this->assertFalse(Unit::where('nama', 'ROLLBACK')->exists());
        $this->assertSame($count, Activity::count());
        Livewire::test(SystemActivityResource::getPages()['index']->getPage())->call('exportCsv')->assertFileDownloaded();
        Livewire::test(SystemActivityResource::getPages()['index']->getPage())->call('exportJson')->assertFileDownloaded();
        $this->assertFalse(SystemActivityResource::canDelete(Activity::firstOrFail()));
    }

    public function test_password_audit_contains_metadata_without_password_or_hash(): void
    {
        $this->admin->update(['password' => Hash::make('original-password-long')]);
        $hash = $this->admin->fresh()->password;
        Activity::query()->delete();
        Livewire::test(\App\Filament\Pages\AccountProfile::class)
            ->set('currentPassword', 'original-password-long')->set('newPassword', 'new-password-long-2026')
            ->set('newPassword_confirmation', 'new-password-long-2026')->call('changePassword')->assertHasNoErrors();
        $this->assertSame(1, Activity::where('event', 'Password Changed')->count());
        $json = Activity::all()->toJson();
        $this->assertStringNotContainsString('original-password-long', $json);
        $this->assertStringNotContainsString('new-password-long-2026', $json);
        $this->assertStringNotContainsString($hash, $json);
        $this->assertStringNotContainsString($this->admin->fresh()->password, $json);
        $other = User::factory()->create();
        Activity::query()->delete();
        Livewire::test(\App\Filament\Resources\UserResource::getPages()['index']->getPage())
            ->callTableAction('edit', $other, data: ['name' => $other->name, 'email' => $other->email,
                'password' => 'administrator-reset-password', 'roles' => [], 'unit_id' => null])->assertHasNoActionErrors();
        $this->assertSame(1, Activity::where('event', 'Password Changed')->count());
        $this->assertStringNotContainsString('administrator-reset-password', Activity::all()->toJson());
        $this->assertStringNotContainsString($other->fresh()->password, Activity::all()->toJson());
    }

    public function test_health_empty_refresh_stale_and_retention_behavior(): void
    {
        Livewire::test(SystemHealth::class)->assertSee('Belum diperiksa')->call('refresh')->assertSuccessful();
        $this->assertSame(3, \Spatie\Health\Models\HealthCheckResultHistoryItem::count());
        $this->assertSame(['ok'], \Spatie\Health\Models\HealthCheckResultHistoryItem::distinct()->pluck('status')->all());
        DB::table('health_check_result_history_items')->update(['created_at' => now()->subMinutes(20)]);
        Livewire::test(SystemHealth::class)->assertSee('Hasil kedaluwarsa');
        DB::table('health_check_result_history_items')->update(['created_at' => now()->subDays(8)]);
        $this->artisan('model:prune', ['--model' => [\Spatie\Health\Models\HealthCheckResultHistoryItem::class]])->assertSuccessful();
        $this->assertSame(0, \Spatie\Health\Models\HealthCheckResultHistoryItem::count());
        $this->assertFalse(config('health.notifications.enabled'));
        $events = app(\Illuminate\Console\Scheduling\Schedule::class)->events();
        $this->assertCount(1, array_filter($events, fn ($event) => str_contains($event->command, 'health:check')));
    }

    public function test_log_viewer_is_read_only_escapes_html_and_skips_large_files(): void
    {
        $original = storage_path();
        $temp = sys_get_temp_dir().'/rka-plugin-logs-'.bin2hex(random_bytes(8));
        mkdir($temp.'/logs', 0775, true);
        $this->app->useStoragePath($temp);
        $small = $temp.'/logs/small.log'; $large = $temp.'/logs/large.log';
        file_put_contents($small, '[2026-11-12 10:00:00] browser-testing.ERROR: <script>unsafe-marker</script>'.PHP_EOL
            .'[2026-11-12 10:00:01] browser-testing.UNKNOWN: malformed level'.PHP_EOL);
        file_put_contents($large, str_repeat('x', 2 * 1024 * 1024 + 1));
        try {
            $provider = app(\AchyutN\FilamentLogViewer\Contracts\LogProvider::class);
            $this->assertNotInstanceOf(\AchyutN\FilamentLogViewer\Contracts\CanDeleteLogs::class, $provider);
            $this->assertCount(1, $provider->getRows());
            $this->assertSame('browser-testing', $provider->getRows()[0]['env']);
            $this->assertSame(['large.log'], $provider->skippedFiles());
            Livewire::test(SystemLogs::class)->assertSee('berkas dilewati')->assertSee('unsafe-marker')
                ->assertDontSeeHtml('<script>unsafe-marker</script>')->assertActionDoesNotExist('clear');
            $this->assertFileExists($small);
        } finally {
            unlink($small); unlink($large); rmdir($temp.'/logs'); rmdir($temp);
            $this->app->useStoragePath($original);
        }
    }

    public function test_assignment_audit_is_not_duplicated_and_foreign_guard_roles_are_rejected(): void
    {
        $target = User::factory()->create();
        $role = Role::findOrCreate('ordinary-role', 'web');
        Activity::query()->delete();
        AccessManagement::syncRoles($target, [$role->id]);
        AccessManagement::syncRoles($target, [$role->id]);
        $this->assertSame(1, Activity::where('event', 'Assignment Changed')->count());
        $foreign = Role::findOrCreate('foreign-role', 'api');
        try { AccessManagement::syncRoles($target, [$foreign->id]); $this->fail('Foreign guard accepted'); }
        catch (ValidationException) {}
        $this->assertSame([$role->id], $target->roles()->pluck('roles.id')->all());
        $this->assertSame(1, Activity::where('event', 'Assignment Changed')->count());
    }

    public function test_failed_storage_check_and_production_command_prohibition(): void
    {
        $original = storage_path();
        $this->app->useStoragePath(sys_get_temp_dir().'/missing-rka-storage-'.bin2hex(random_bytes(8)));
        try {
            $this->assertSame('failed', \App\Health\StorageWritableCheck::new()->run()->status->value);
        } finally { $this->app->useStoragePath($original); }
        \BezhanSalleh\FilamentShield\Facades\FilamentShield::prohibitDestructiveCommands(true);
        try {
            $this->artisan('shield:generate', ['--panel' => 'admin', '--all' => true])->assertFailed();
            $this->artisan('shield:setup')->assertFailed();
        } finally { \BezhanSalleh\FilamentShield\Facades\FilamentShield::prohibitDestructiveCommands(false); }
    }

    public function test_login_logout_and_failed_authentication_are_audited_without_credentials(): void
    {
        Activity::query()->delete();
        event(new \Illuminate\Auth\Events\Login('web', $this->admin, false));
        event(new \Illuminate\Auth\Events\Failed('web', null, ['email' => 'invalid@example.test', 'password' => 'never-store-this-secret']));
        event(new \Illuminate\Auth\Events\Logout('web', $this->admin));
        $this->assertSame(3, Activity::where('log_name', 'Access')->count());
        $this->assertStringNotContainsString('never-store-this-secret', Activity::all()->toJson());
    }
}
