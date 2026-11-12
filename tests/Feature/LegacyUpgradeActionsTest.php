<?php

namespace Tests\Feature;

use App\Filament\Resources\{RkaResource, RkaMaintenanceResource, RkaConsumableResource, RkaOperasionalResource, RkaPendapatanResource, RkaGajiResource};
use App\Models\{Setting, Unit, User};
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyUpgradeActionsTest extends TestCase
{
    use RefreshDatabase;

    private const RESOURCES = [RkaResource::class, RkaMaintenanceResource::class, RkaConsumableResource::class,
        RkaOperasionalResource::class, RkaPendapatanResource::class, RkaGajiResource::class];

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->withoutVite();
    }

    public function test_all_six_budget_modules_create_edit_duplicate_and_delete_through_filament(): void
    {
        $unit = Unit::create(['nama' => 'Unit A']);
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $this->actingAs($user);
        foreach (self::RESOURCES as $resource) {
            $model = $resource::getModel();
            $page = $resource::getPages()['index']->getPage();
            $data = ['uraian' => 'Budget item', 'unit_nama' => $unit->id, 'is_parent' => false,
                'is_detail' => false, 'volume' => 2, 'harga_satuan' => 12.5, 'total' => 25];
            $component = Livewire::test($page)->callAction('create', data: $data)->assertHasNoActionErrors();
            $record = $model::firstOrFail();
            $this->assertSame((string) $unit->id, (string) $record->unit_nama);
            $this->assertEquals(25, $record->total);
            $component->callTableAction('edit', $record, data: [...$data, 'uraian' => 'Edited item', 'volume' => 3, 'total' => 37.5])
                ->assertHasNoTableActionErrors();
            $this->assertSame('Edited item', $record->fresh()->uraian);
            $this->assertEquals(37.5, $record->fresh()->total);
            $component->callTableAction('duplikat', $record)->assertHasNoTableActionErrors();
            $copy = $model::where('uraian', 'Edited item COPY')->firstOrFail();
            $this->assertSame(1, \Spatie\Activitylog\Models\Activity::where('subject_type', $model)->where('subject_id', $copy->id)->where('event', 'Replicated')->count());
            $component->callTableAction('delete', $copy)->assertHasNoTableActionErrors();
            $this->assertNull($copy->fresh());
            $this->assertSame(1, $model::count());
        }
    }

    public function test_inline_changes_are_scoped_and_disabled_when_budget_is_locked(): void
    {
        $a = Unit::create(['nama' => 'Unit A']);
        $b = Unit::create(['nama' => 'Unit B']);
        $user = User::factory()->create(['unit_id' => $a->id]);
        $user->is_editmode = true;
        $user->save();
        $this->actingAs($user);
        foreach (self::RESOURCES as $resource) {
            $model = $resource::getModel();
            $data = ['uraian' => 'Original', 'is_parent' => false, 'is_detail' => false, 'volume' => 2, 'harga_satuan' => 5, 'total' => 10];
            if ($resource === RkaGajiResource::class) unset($data['is_parent'], $data['volume'], $data['harga_satuan']);
            $own = $model::create([...$data, 'unit_nama' => (string) $a->id]);
            $foreign = $model::create([...$data, 'unit_nama' => (string) $b->id]);
            $page = $resource::getPages()['index']->getPage();
            $component = Livewire::test($page);
            $component->call('updateTableColumnState', 'uraian', (string) $own->id, 'Edited inline');
            $this->assertSame('Edited inline', $own->fresh()->uraian);
            $component->call('updateTableColumnState', 'uraian', (string) $foreign->id, 'Forbidden');
            $this->assertSame('Original', $foreign->fresh()->uraian);
            Setting::initializeDefaults();
            Setting::findOrFail(1)->update(['valbool' => true]);
            Livewire::test($page)->call('updateTableColumnState', 'uraian', (string) $own->id, 'Locked');
            $this->assertSame('Edited inline', $own->fresh()->uraian);
            $this->assertFalse($resource::canCreate());
            $this->assertFalse($resource::canEdit($own));
            $this->assertFalse($resource::canDelete($own));
            Setting::findOrFail(1)->update(['valbool' => false]);
        }
    }

    public function test_edit_mode_updates_the_table_immediately_and_can_be_toggled_back(): void
    {
        $unit = Unit::create(['nama' => 'Mode unit']);
        $user = User::factory()->create(['unit_id' => $unit->id]);
        $this->actingAs($user);
        $page = RkaResource::getPages()['index']->getPage();
        $component = Livewire::test($page)->callAction('toggleEditMode')->assertHasNoActionErrors();
        $this->assertTrue($user->fresh()->is_editmode);
        $component->assertSee('Mode: Edit')->callAction('toggleEditMode')->assertHasNoActionErrors()->assertSee('Mode: Normal');
        $this->assertFalse($user->fresh()->is_editmode);
    }

    public function test_500_row_pagination_keeps_all_records_and_does_not_query_settings_per_row(): void
    {
        $unit = Unit::create(['nama' => 'Large unit']);
        $this->actingAs(User::factory()->create(['unit_id' => $unit->id]));
        $rows = [];
        for ($i = 0; $i < 501; $i++) $rows[] = [
            'uraian' => 'Budget '.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'unit_nama' => (string) $unit->id,
            'is_parent' => false, 'is_detail' => true, 'volume' => '1', 'harga_satuan' => '2', 'total' => '2.00',
        ];
        \App\Models\Rka::insert($rows);
        $settingsQueries = 0;
        DB::listen(function ($query) use (&$settingsQueries) {
            if (preg_match('/from ["`]?settings["`]?/i', $query->sql)) $settingsQueries++;
        });
        $component = Livewire::test(RkaResource::getPages()['index']->getPage())->set('tableRecordsPerPage', 500);
        $first = $component->instance()->getTableRecords();
        $this->assertCount(500, $first);
        $this->assertSame(501, $first->total());
        $component->call('gotoPage', 2);
        $second = $component->instance()->getTableRecords();
        $this->assertCount(1, $second);
        $this->assertNotContains($second->first()->id, $first->modelKeys());
        $this->assertLessThanOrEqual(5, $settingsQueries);
        $component->call('gotoPage', 1)->callAction('toggleEditMode')->assertHasNoActionErrors()->assertSee('Mode: Edit');
        $this->assertCount(500, $component->instance()->getTableRecords());
    }
}
