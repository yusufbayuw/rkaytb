<?php

namespace Tests\Feature;

use App\Filament\Resources\{UserResource, RoleResource};
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class MasterDataUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->withoutVite();
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('superadmin', 'web'));
        $this->actingAs($admin);
    }

    public function test_retired_employee_modules_have_no_routes_resources_models_or_event_listener(): void
    {
        $resources = Filament::getPanel('admin')->getResources();
        foreach (['Employee', 'EmployeeKpiScore', 'KpiTarget', 'Position'] as $model) {
            $this->assertFalse(class_exists('App\\Models\\'.$model));
            $this->assertNotContains('App\\Filament\\Resources\\'.$model.'Resource', $resources);
        }
        foreach (['employees', 'employee-kpi-scores', 'kpi-targets', 'positions'] as $slug) {
            $this->get('/admin/'.$slug)->assertNotFound();
            $this->assertFalse(\Illuminate\Support\Facades\Route::has('filament.admin.resources.'.$slug.'.index'));
        }
        $this->assertFalse(\Illuminate\Support\Facades\Event::hasListeners('App\\Events\\EmployeePositionCreated'));
    }

    public function test_historical_audit_of_retired_models_remains_readable_and_exportable(): void
    {
        $activities = collect(['Employee', 'EmployeeKpiScore', 'KpiTarget', 'Position'])->map(fn ($model) =>
            \Spatie\Activitylog\Models\Activity::create([
                'log_name' => 'Historical HR', 'event' => 'created', 'description' => 'Retired '.$model,
                'subject_type' => 'App\\Models\\'.$model, 'subject_id' => 42,
                'properties' => ['attributes' => ['nama' => 'Historical record']],
            ])
        );
        $resource = \App\Filament\Resources\SystemActivityResource::class;
        foreach ($activities as $activity) {
            $this->get($resource::getUrl('view', ['record' => $activity]))->assertOk()->assertSee($activity->description);
        }
        $component = Livewire::test($resource::getPages()['index']->getPage())->assertCanSeeTableRecords($activities);
        $component->call('exportCsv')->assertFileDownloaded();
        $component->call('exportJson')->assertFileDownloaded();
        $exporter = app(\MrAdder\FilamentLogger\Support\ActivityExporter::class);
        foreach (['csv', 'json'] as $format) {
            $stream = fopen('php://temp', 'w+');
            try {
                $this->assertSame(4, $exporter->writeTo($stream, $format,
                    \Spatie\Activitylog\Models\Activity::where('log_name', 'Historical HR')->with('subject')));
                rewind($stream);
                $content = stream_get_contents($stream);
                foreach (['Employee', 'EmployeeKpiScore', 'KpiTarget', 'Position'] as $model) {
                    $this->assertStringContainsString($model, $content);
                }
                $this->assertStringContainsString('Historical record', $content);
            } finally { fclose($stream); }
        }
        $this->assertSame(4, \Spatie\Activitylog\Models\Activity::where('log_name', 'Historical HR')->count());
    }

    public function test_roles_permissions_and_user_without_legacy_unit_can_be_created_in_filament(): void
    {
        $permission = Permission::create(['name' => 'read:unit', 'guard_name' => 'web']);
        Livewire::test(RoleResource::getPages()['create']->getPage())->fillForm([
            'name' => 'new-role', 'guard_name' => 'web', \App\Filament\Resources\UnitResource::class => ['read:unit'],
        ])->call('create')->assertHasNoFormErrors();
        $role = Role::findByName('new-role', 'web');
        $this->assertTrue($role->hasPermissionTo('read:unit'));
        Livewire::test(UserResource::getPages()['index']->getPage())->callAction('create', data: [
            'name' => 'New Member', 'email' => 'member@example.test', 'password' => 'A-long-test-password',
            'unit_id' => null, 'roles' => [$role->id],
        ])->assertHasNoActionErrors();
        $user = User::where('email', 'member@example.test')->firstOrFail();
        $this->assertNull($user->unit_id);
        $this->assertTrue($user->hasRole('new-role'));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('A-long-test-password', $user->password));
        Livewire::test(UserResource::getPages()['index']->getPage())->callAction('create', data: [
            'name' => 'Duplicate Member', 'email' => 'member@example.test', 'password' => 'A-long-test-password',
            'unit_id' => null, 'roles' => [$role->id],
        ])->assertHasActionErrors(['email' => 'unique']);
        Livewire::test(UserResource::getPages()['index']->getPage())->callTableAction('edit', $user, data: [
            'name' => 'Updated Member', 'email' => 'member@example.test', 'password' => '',
            'unit_id' => null, 'roles' => [$role->id],
        ])->assertHasNoActionErrors();
        $this->assertSame('Updated Member', $user->refresh()->name);
    }

    public function test_sanctum_user_endpoint_retains_its_authentication_contract(): void
    {
        auth()->forgetGuards();
        $this->getJson('/api/user')->assertUnauthorized();
        $user = User::factory()->create();
        $token = $user->createToken('upgrade-test')->plainTextToken;
        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('id', $user->id);
    }
}
