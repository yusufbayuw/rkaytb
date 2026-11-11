<?php

namespace Tests\Feature;

use App\Models\Rka;
use App\Models\Unit;
use App\Models\User;
use App\Models\RkaPlatform\{Organization, Membership};
use App\Filament\Resources\{
    RkaResource, RkaMaintenanceResource, RkaConsumableResource,
    RkaOperasionalResource, RkaGajiResource, RkaPendapatanResource
};
use App\Filament\Resources\RkaResource\Widgets\TotalOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Illuminate\Validation\ValidationException;

class RkaPlatformLegacyFinanceRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function row(string $name, bool $parent, ?Rka $under = null, string $volume = '0', string $price = '0'): Rka
    {
        return Rka::create([
            'uraian' => $name, 'is_parent' => $parent, 'is_detail' => false,
            'unit_nama' => '1', 'parent_id' => $under?->id,
            'volume' => $volume, 'harga_satuan' => $price,
        ]);
    }

    public function test_editing_volume_and_price_updates_leaf_and_all_parent_totals(): void
    {
        $root = $this->row('Root', true);
        $middle = $this->row('Section', true, $root);
        $leafA = $this->row('Asset A', false, $middle, '2', '150.50');
        $leafB = $this->row('Asset B', false, $middle, '1', '99.00');

        $this->assertSame('301.00', $leafA->fresh()->total);
        $this->assertEquals(400, (float)$root->fresh()->total);
        $leafA->update(['volume' => '3']);
        $this->assertSame('451.50', $leafA->fresh()->total);
        $this->assertSame('550.50', $middle->fresh()->total);
        $this->assertSame('550.50', $root->fresh()->total);

        $leafB->update(['harga_satuan' => '125.00']);
        $this->assertSame('576.50', $root->fresh()->total);
        $this->assertSame('576.50', $root->fresh()->total_view);
        $this->assertSame('lima ratus tujuh puluh enam', strtolower($root->fresh()->total_terbilang));
        $this->assertEquals(576.50, (float)Rka::isLeaf()->sum('total'));
    }

    public function test_reading_the_investment_table_does_not_change_database_timestamps(): void
    {
        $root = $this->row('Root', true);
        $leaf = $this->row('Child', false, $root, '2', '15.00');
        $oldTimestamp = $root->fresh()->updated_at;
        $oldTotal = $root->fresh()->total;

        $this->assertSame('30.00', $root->childrenScoreSum('total') === null
            ? '0.00' : number_format((float)$root->childrenScoreSum('total'), 2, '.', ''));
        $this->assertSame($oldTimestamp?->toDateTimeString(), $root->fresh()->updated_at?->toDateTimeString());
        $this->assertSame($oldTotal, $root->fresh()->total);
        $this->assertSame('30.00', $leaf->fresh()->total);
    }

    public function test_deleting_or_moving_an_asset_recalculates_both_affected_branches(): void
    {
        $rootA = $this->row('Group A', true);
        $rootB = $this->row('Group B', true);
        $leaf = $this->row('Item', false, $rootA, '2', '25.00');
        $this->assertSame('50.00', $rootA->fresh()->total);
        $leaf->update(['parent_id' => $rootB->id]);
        $this->assertSame('0.00', $rootA->fresh()->total);
        $this->assertSame('50.00', $rootB->fresh()->total);
        $leaf->delete();
        $this->assertSame('0.00', $rootB->fresh()->total);
    }

    public function test_unassigned_rka_only_user_cannot_access_any_old_finance_resource(): void
    {
        $user = User::factory()->create(['unit_id' => null]);
        $org = Organization::create(['name' => 'Independent entity', 'slug' => 'independent-entity']);
        Membership::create(['organization_id' => $org->id, 'user_id' => $user->id,
            'roles' => ['contributor'], 'active' => true]);
        $this->actingAs($user);
        $this->assertTrue($user->canAccessFilament());

        foreach ([
            RkaResource::class, RkaMaintenanceResource::class, RkaConsumableResource::class,
            RkaOperasionalResource::class, RkaGajiResource::class, RkaPendapatanResource::class,
        ] as $resource) {
            $this->assertFalse($resource::canViewAny(), $resource.' must not expose legacy data');
            $this->assertFalse($resource::canCreate(), $resource.' must not allow creating legacy data');
        }
        Livewire::actingAs($user)->test(TotalOverview::class)
            ->assertSee('Unit Belum Ditugaskan');
    }

    public function test_legacy_unit_user_and_finance_role_keep_access_to_finance_screens(): void
    {
        $unit = Unit::create(['nama' => 'Legacy unit']);
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $this->actingAs($user);
        $this->assertTrue(\App\Support\LegacyRkaAccess::canView($user));

        $global = User::factory()->create(['unit_id' => null]);
        Role::findOrCreate('admin-keuangan', 'web');
        $global->assignRole('admin-keuangan');
        $this->assertTrue(\App\Support\LegacyRkaAccess::canView($global));
    }
    public function test_leaf_cannot_be_reparented_across_units_or_into_its_descendant(): void
    {
        $unitA = $this->row('Unit A Root', true);
        $unitB = Rka::create([
            'uraian' => 'Unit B Root', 'is_parent' => true,
            'is_detail' => false, 'unit_nama' => '2',
        ]);
        $leaf = $this->row('Unit A Item', false, $unitA, '2', '10.00');
        try {
            $leaf->update(['parent_id' => $unitB->id]);
            $this->fail('Cross-unit parent must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('parent_id', $e->errors());
        }
        $this->assertSame($unitA->id, $leaf->fresh()->parent_id);
        $this->assertSame('20.00', $unitA->fresh()->total);

        $section = $this->row('Section', true, $unitA);
        try {
            $unitA->update(['parent_id' => $section->id]);
            $this->fail('Cyclic hierarchy must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('parent_id', $e->errors());
        }
        $this->assertNull($unitA->fresh()->parent_id);
    }

    public function test_parent_cannot_be_deleted_with_children_or_changed_into_leaf(): void
    {
        $parent = $this->row('Parent', true);
        $child = $this->row('Child', false, $parent, '1', '40.00');
        try {
            $parent->delete();
            $this->fail('Deleting parent with children must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('parent_id', $e->errors());
        }
        try {
            $parent->update(['is_parent' => false]);
            $this->fail('Parent with children cannot become leaf.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('is_parent', $e->errors());
        }
        $this->assertNotNull($parent->fresh());
        $this->assertNotNull($child->fresh());
        $this->assertSame('40.00', $parent->fresh()->total);
    }

    public function test_manual_override_of_derived_total_is_recomputed_from_inputs(): void
    {
        $root = $this->row('Root', true);
        $leaf = $this->row('Item', false, $root, '2', '12.50');
        $leaf->update(['total' => '999999.00','total_view'=>'tampered']);
        $this->assertSame('25.00', $leaf->fresh()->total);
        $this->assertSame('25.00', $root->fresh()->total);
    }

    public function test_direct_record_actions_reject_another_legacy_unit(): void
    {
        $unitOne = Unit::create(['nama' => 'Unit One']);
        $unitTwo = Unit::create(['nama' => 'Unit Two']);
        $user = User::factory()->create(['unit_id' => $unitOne->id]);
        $this->actingAs($user);
        $foreign = Rka::create([
            'uraian' => 'Other unit item', 'is_parent' => false,
            'is_detail' => false, 'unit_nama' => (string)$unitTwo->id,
            'volume' => '1', 'harga_satuan' => '25',
        ]);
        foreach ([
            RkaResource::class, RkaMaintenanceResource::class, RkaConsumableResource::class,
            RkaOperasionalResource::class, RkaGajiResource::class, RkaPendapatanResource::class,
        ] as $resource) {
            $this->assertFalse($resource::canView($foreign), $resource.' view must be scoped');
            $this->assertFalse($resource::canEdit($foreign), $resource.' edit must be scoped');
            $this->assertFalse($resource::canDelete($foreign), $resource.' delete must be scoped');
        }
    }

}
