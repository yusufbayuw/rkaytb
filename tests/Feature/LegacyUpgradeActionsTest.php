<?php

namespace Tests\Feature;

use App\Filament\Resources\RkaResource;
use App\Models\{RkaCategory, RkaEntry, Setting, Unit, User};
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class LegacyUpgradeActionsTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->withoutVite();
    }

    public function test_all_six_budget_categories_create_edit_duplicate_and_delete_through_one_filament_resource(): void
    {
        $unit = Unit::create(['nama' => 'Unit A']);
        $this->actingAs(User::factory()->create(['unit_id' => $unit->id]));
        foreach (RkaCategory::all() as $category) {
            $manual = $category->calculation_mode === 'manual';
            $data = ['category_id' => $category->id, 'uraian' => 'Budget item', 'unit_nama' => $unit->id,
                'is_parent' => false, 'is_detail' => false, ...($manual ? ['total' => 25] : ['volume' => 2, 'harga_satuan' => 12.5])];
            $component = Livewire::test(RkaResource::getPages()['index']->getPage())
                ->set('activeTab', (string) $category->id)->callAction('create', data: $data)->assertHasNoActionErrors();
            $record = RkaEntry::where('category_id', $category->id)->firstOrFail();
            $this->assertSame((string) $unit->id, (string) $record->unit_nama);
            $this->assertEquals(25, $record->total);
            $component->callTableAction('edit', $record, data: [...$data, 'uraian' => 'Edited item', ...($manual ? ['total' => 37.5] : ['volume' => 3])])
                ->assertHasNoTableActionErrors();
            $this->assertSame('Edited item', $record->fresh()->uraian);
            $this->assertEquals(37.5, $record->fresh()->total);
            $component->callTableAction('duplikat', $record)->assertHasNoTableActionErrors();
            $copy = RkaEntry::where('category_id', $category->id)->where('uraian', 'Edited item COPY')->firstOrFail();
            $this->assertSame(1, \Spatie\Activitylog\Models\Activity::where('subject_type', RkaEntry::class)->where('subject_id', $copy->id)->where('event', 'Replicated')->count());
            $this->assertNull($copy->origin_source);
            $this->assertNotSame($record->document_number, $copy->document_number);
            $component->callTableAction('delete', $copy)->assertHasNoTableActionErrors();
            $this->assertNull($copy->fresh());
            $this->assertSame(1, RkaEntry::where('category_id', $category->id)->count());
        }
    }

    public function test_inline_changes_are_scoped_and_disabled_when_budget_is_locked(): void
    {
        $a = Unit::create(['nama' => 'Unit A']); $b = Unit::create(['nama' => 'Unit B']);
        $user = User::factory()->create(['unit_id' => $a->id]);
        $user->is_editmode = true; $user->save(); $this->actingAs($user);
        foreach (RkaCategory::all() as $category) {
            $data = ['category_id' => $category->id, 'uraian' => 'Original', 'is_parent' => false,
                ...($category->calculation_mode === 'manual' ? ['total' => 10] : ['volume' => 2, 'harga_satuan' => 5])];
            $own = RkaEntry::create([...$data, 'unit_nama' => (string) $a->id]);
            $foreign = RkaEntry::create([...$data, 'unit_nama' => (string) $b->id]);
            $page = RkaResource::getPages()['index']->getPage();
            $component = Livewire::test($page)->set('activeTab', (string) $category->id);
            $component->call('updateTableColumnState', 'uraian', (string) $own->id, 'Edited inline');
            $this->assertSame('Edited inline', $own->fresh()->uraian);
            $component->call('updateTableColumnState', 'uraian', (string) $foreign->id, 'Forbidden');
            $this->assertSame('Original', $foreign->fresh()->uraian);
            Setting::initializeDefaults(); Setting::findOrFail(1)->update(['valbool' => true]);
            Livewire::test($page)->call('updateTableColumnState', 'uraian', (string) $own->id, 'Locked');
            $this->assertSame('Edited inline', $own->fresh()->uraian);
            $this->assertFalse(RkaResource::canCreate());
            $this->assertFalse(RkaResource::canEdit($own));
            $this->assertFalse(RkaResource::canDelete($own));
            Setting::findOrFail(1)->update(['valbool' => false]);
        }
    }

    public function test_edit_mode_updates_the_table_immediately_and_can_be_toggled_back(): void
    {
        $unit = Unit::create(['nama' => 'Mode unit']);
        $user = User::factory()->create(['unit_id' => $unit->id]); $this->actingAs($user);
        $component = Livewire::test(RkaResource::getPages()['index']->getPage())->callAction('toggleEditMode')->assertHasNoActionErrors();
        $this->assertTrue($user->fresh()->is_editmode);
        $component->assertSee('Mode: Edit')->callAction('toggleEditMode')->assertHasNoActionErrors()->assertSee('Mode: Normal');
        $this->assertFalse($user->fresh()->is_editmode);
    }

    public function test_500_row_pagination_keeps_all_records_and_does_not_query_settings_per_row(): void
    {
        $unit = Unit::create(['nama' => 'Large unit']);
        $this->actingAs(User::factory()->create(['unit_id' => $unit->id]));
        $category = RkaCategory::where('code', 'investment')->firstOrFail();
        $rows = [];
        for ($i = 0; $i < 501; $i++) $rows[] = ['category_id' => $category->id, 'document_number' => $i + 1,
            'uraian' => 'Budget '.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'unit_nama' => (string) $unit->id,
            'is_parent' => false, 'is_detail' => true, 'volume' => '1', 'harga_satuan' => '2', 'total' => '2.00'];
        RkaEntry::insert($rows);
        $settingsQueries = 0;
        DB::listen(function ($query) use (&$settingsQueries) { if (preg_match('/from ["`]?settings["`]?/i', $query->sql)) $settingsQueries++; });
        $component = Livewire::test(RkaResource::getPages()['index']->getPage())->set('tableRecordsPerPage', 500);
        $first = $component->instance()->getTableRecords();
        $this->assertCount(500, $first); $this->assertSame(501, $first->total());
        $component->call('gotoPage', 2); $second = $component->instance()->getTableRecords();
        $this->assertCount(1, $second); $this->assertNotContains($second->first()->id, $first->modelKeys());
        $this->assertLessThanOrEqual(5, $settingsQueries);
        $component->call('gotoPage', 1)->callAction('toggleEditMode')->assertHasNoActionErrors()->assertSee('Mode: Edit');
        $this->assertCount(500, $component->instance()->getTableRecords());
    }
}
