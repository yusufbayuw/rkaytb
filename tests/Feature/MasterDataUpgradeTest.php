<?php

namespace Tests\Feature;

use App\Filament\Resources\{EmployeeResource, UserResource, RoleResource};
use App\Models\{Employee, EmployeeKpiScore, KpiTarget, Position, Unit, User};
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

    public function test_creating_employee_from_filament_creates_each_target_score_once(): void
    {
        $unit = Unit::create(['nama' => 'Unit']);
        $position = Position::create(['nama' => 'Position']);
        $targets = collect(['Quality', 'Delivery'])->map(fn ($name) => KpiTarget::create(['nama' => $name]));
        $position->kpiTargets()->sync($targets->pluck('id')->all());
        $this->assertSame(1, $targets->first()->positions()->count());
        Livewire::test(EmployeeResource::getPages()['index']->getPage())->callAction('create', data: [
            'nama' => 'New Employee', 'position_id' => $position->id, 'unit_id' => $unit->id,
        ])->assertHasNoActionErrors();
        $employee = Employee::firstOrFail();
        $this->assertSame(2, $employee->kpiScores()->count());
        $this->assertSame(2, EmployeeKpiScore::count());
        $this->assertEquals(0, $employee->kpiScores()->sum('score'));
        $this->assertEquals(0, $employee->kpiScores()->sum('subtotal'));
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
