<?php

namespace Tests\Feature;

use App\Filament\Resources\{RkaResource, RkaCategoryResource};
use App\Models\{RkaCategory, RkaEntry, Unit, User};
use App\Services\{RkaConsolidation, RkaSummary};
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnifiedRkaTest extends TestCase
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

    private function category(string $code): RkaCategory { return RkaCategory::where('code', $code)->firstOrFail(); }

    private function entry(RkaCategory $category, Unit $unit, array $data = []): RkaEntry
    {
        return RkaEntry::create(['category_id' => $category->id, 'unit_nama' => (string) $unit->id,
            'uraian' => $category->name.' '.$unit->nama,
            ...($category->calculation_mode === 'manual' ? ['total' => '25.75'] : ['volume' => '2', 'harga_satuan' => '12.875']), ...$data]);
    }

    private function rejected(callable $action, string $field): void
    {
        try { $action(); $this->fail('Invalid RKA mutation accepted'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey($field, $exception->errors()); }
    }

    public function test_consolidation_preserves_six_sources_ids_values_dates_and_parent_mapping_without_rewriting_archives(): void
    {
        $unit = Unit::create(['nama' => 'Migration unit']);
        $before = [];
        foreach (RkaConsolidation::CATEGORIES as $code => [$table]) {
            $row = ['id' => 1, 'uraian' => 'Historical '.$code, 'unit_nama' => (string) $unit->id,
                'nomor_akun' => '00123', 'is_detail' => true, 'total' => '123.45', 'januari' => '0.10',
                'created_at' => '2020-01-02 03:04:05', 'updated_at' => '2021-02-03 04:05:06'];
            if ($code !== 'salary') {
                $row += ['is_parent' => true, 'volume' => null, 'harga_satuan' => null];
                DB::table($table)->insert($row);
                DB::table($table)->insert([...$row, 'id' => 2, 'uraian' => 'Historical child '.$code,
                    'is_parent' => false, 'parent_id' => 1, 'volume' => '2.0000', 'harga_satuan' => '1.2500']);
            } else DB::table($table)->insert($row);
            $before[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        }
        app(RkaConsolidation::class)->import();
        $this->assertSame(11, RkaEntry::count());
        foreach ($before as $table => $rows) {
            $this->assertSame($rows, DB::table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());
            foreach ($rows as $row) {
                $entry = RkaEntry::where('origin_source', $table)->where('origin_id', $row['id'])->firstOrFail();
                $this->assertEquals($row, $entry->origin_snapshot);
                foreach (RkaConsolidation::FIELDS as $field) {
                    if (array_key_exists($field, $row)) $this->assertEquals($row[$field], $entry->getRawOriginal($field), $table.'.'.$field);
                }
                if (isset($row['parent_id'])) {
                    $this->assertSame(1, (int) $entry->parent->origin_id);
                    $this->assertSame($table, $entry->parent->origin_source);
                    $this->assertSame($entry->category_id, $entry->parent->category_id);
                }
            }
        }
        $ledger = DB::table('rka_entries')->orderBy('id')->get()->toJson();
        app(RkaConsolidation::class)->import();
        $this->assertSame($ledger, DB::table('rka_entries')->orderBy('id')->get()->toJson());
        app(RkaConsolidation::class)->assertRollbackSafe();
    }

    public function test_all_categories_use_exact_leaf_calculation_and_monthly_parent_aggregation_on_edit_move_and_delete(): void
    {
        $unit = Unit::create(['nama' => 'Financial unit']);
        foreach (RkaCategory::all() as $category) {
            $data = ['category_id' => $category->id, 'unit_nama' => (string) $unit->id];
            $root = RkaEntry::create([...$data, 'uraian' => 'Root', 'is_parent' => true]);
            $other = RkaEntry::create([...$data, 'uraian' => 'Other root', 'is_parent' => true]);
            $values = $category->calculation_mode === 'manual' ? ['total' => '1.01'] : ['volume' => '1.0050', 'harga_satuan' => '1'];
            $leaf = RkaEntry::create([...$data, ...$values, 'uraian' => 'Leaf', 'parent_id' => $root->id, 'januari' => '0.10', 'februari' => '0.20']);
            $this->assertSame('1.01', $leaf->total);
            $this->assertSame('1.01', $root->fresh()->total);
            $this->assertSame('0.10', $root->fresh()->januari);
            $this->assertSame('0.71', $leaf->monthlyBalance());
            $leaf->update($category->calculation_mode === 'manual' ? ['total' => '2.01'] : ['volume' => '2.0050']);
            $this->assertSame('2.01', $root->fresh()->total);
            $leaf->update(['parent_id' => $other->id]);
            $this->assertSame('0.00', $root->fresh()->total);
            $this->assertSame('2.01', $other->fresh()->total);
            $leaf->delete();
            $this->assertSame('0.00', $other->fresh()->total);
        }
    }

    public function test_one_resource_renders_and_creates_a_new_dynamic_category_and_budget_from_filament(): void
    {
        $this->admin();
        $unit = Unit::create(['nama' => 'Dynamic unit']);
        $this->assertCount(9, Filament::getPanel('admin')->getResources());
        $this->get(RkaResource::getUrl())->assertOk();
        Livewire::test(RkaCategoryResource::getPages()['index']->getPage())->callAction('create', data: [
            'name' => 'Pelatihan', 'code' => 'training', 'direction' => 'expense', 'calculation_mode' => 'manual',
            'document_prefix' => 'PLT', 'sort_order' => 7, 'is_active' => true,
        ])->assertHasNoActionErrors();
        $category = $this->category('training');
        Livewire::test(RkaResource::getPages()['index']->getPage())->set('activeTab', (string) $category->id)
            ->callAction('create', data: ['category_id' => $category->id, 'unit_nama' => $unit->id,
                'uraian' => 'Pelatihan baru', 'is_parent' => false, 'total' => '1250.75', 'januari' => '1250.75'])
            ->assertHasNoActionErrors();
        $entry = RkaEntry::where('uraian', 'Pelatihan baru')->firstOrFail();
        $this->assertSame('1250.75', $entry->total);
        $this->assertSame($category->id, $entry->category_id);
        $this->assertNull($entry->origin_source);
        $this->assertSame('PLT-'.str_pad((string) $entry->document_number, 6, '0', STR_PAD_LEFT), $entry->documentCode());
        $totals = app(RkaSummary::class)->totals(RkaEntry::query());
        $this->assertSame('1250.75', $totals['expense']);
    }

    public function test_document_numbers_preserve_sparse_legacy_ids_and_are_never_reused_after_deletion(): void
    {
        $unit = Unit::create(['nama' => 'Document numbers']);
        DB::table('rkas')->insert(['id' => 300, 'uraian' => 'Sparse investment', 'unit_nama' => (string) $unit->id,
            'is_parent' => false, 'volume' => '1', 'harga_satuan' => '2', 'total' => '2.00']);
        DB::table('rka_gajis')->insert(['id' => 300, 'uraian' => 'Sparse salary', 'unit_nama' => (string) $unit->id, 'total' => '3.00']);
        app(RkaConsolidation::class)->import();
        $old = RkaEntry::where('origin_source', 'rkas')->firstOrFail();
        $salary = RkaEntry::where('origin_source', 'rka_gajis')->firstOrFail();
        $this->assertSame('Inv-000300', $old->documentCode());
        $this->assertSame('Gaj-000300', $salary->documentCode());
        $new = $this->entry($this->category('investment'), $unit);
        $this->assertSame('Inv-000301', $new->documentCode());
        $new->delete();
        $this->assertSame('Inv-000302', $this->entry($this->category('investment'), $unit)->documentCode());
        $this->assertSame('2.00', DB::table('rkas')->where('id', 300)->value('total'));
    }

    public function test_invalid_legacy_hierarchy_remains_visible_with_original_parent_and_values_preserved(): void
    {
        $a = Unit::create(['nama' => 'Legacy A']); $b = Unit::create(['nama' => 'Legacy B']);
        foreach ([[1, 2, true, $a->id], [2, 1, true, $a->id], [3, 999, false, $a->id], [4, null, true, $b->id], [5, 4, false, $a->id]] as [$id, $parent, $container, $unit]) {
            DB::table('rkas')->insert(['id' => $id, 'parent_id' => $parent, 'is_parent' => $container,
                'unit_nama' => (string) $unit, 'uraian' => 'Broken legacy '.$id, 'total' => '17.25']);
        }
        $before = DB::table('rkas')->orderBy('id')->get()->toJson();
        app(RkaConsolidation::class)->import(); $this->admin();
        $this->assertSame(5, RkaResource::getEloquentQuery()->count());
        foreach ([1, 2, 3, 5] as $id) {
            $entry = RkaEntry::where('origin_source', 'rkas')->where('origin_id', $id)->firstOrFail();
            $this->assertNull($entry->parent_id); $this->assertNotEmpty($entry->migration_issues);
            $this->assertSame('17.25', $entry->total);
        }
        $this->assertSame(4, RkaEntry::where('origin_id', 5)->firstOrFail()->origin_snapshot['parent_id']);
        $this->assertSame($before, DB::table('rkas')->orderBy('id')->get()->toJson());
    }

    public function test_unit_access_finance_salary_exception_and_export_authorization_hold_for_all_categories(): void
    {
        $a = Unit::create(['nama' => 'Scope A']); $b = Unit::create(['nama' => 'Scope B']);
        $own = []; $foreign = [];
        foreach (RkaCategory::all() as $category) { $own[] = $this->entry($category, $a); $foreign[] = $this->entry($category, $b); }
        $operator = User::factory()->create(['unit_id' => $a->id]); $this->actingAs($operator);
        $this->assertEqualsCanonicalizing(array_column($own, 'id'), RkaResource::getEloquentQuery()->pluck('id')->all());
        foreach ($foreign as $entry) {
            $this->assertFalse(RkaResource::canView($entry)); $this->assertFalse(RkaResource::canEdit($entry)); $this->assertFalse(RkaResource::canDelete($entry));
        }
        try {
            app(\App\Services\LegacyExportService::class)->dataset(RkaResource::class, RkaEntry::where('unit_nama', $b->id),
                ['total' => ['label' => 'Total', 'numeric' => true, 'value' => fn ($record) => $record->total]], ['total']);
            $this->fail('Foreign unit exported');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { $this->assertSame(403, $exception->getStatusCode()); }
        $finance = User::factory()->create(['unit_id' => $a->id]); $finance->assignRole(Role::findOrCreate('admin-keuangan', 'web')); $this->actingAs($finance);
        $expected = [...array_column($own, 'id'), collect($foreign)->first(fn ($e) => $e->category_id === $this->category('salary')->id)->id];
        $this->assertEqualsCanonicalizing($expected, RkaResource::getEloquentQuery()->pluck('id')->all());
        $this->assertFalse(RkaResource::canCreate());
        foreach ($foreign as $entry) {
            $this->assertSame($entry->category_id === $this->category('salary')->id, RkaResource::canView($entry));
            $this->assertFalse(RkaResource::canEdit($entry)); $this->assertFalse(RkaResource::canDelete($entry));
        }
    }

    public function test_parent_category_unit_cycles_and_source_identity_cannot_be_forged(): void
    {
        $a = Unit::create(['nama' => 'Ancestry A']); $b = Unit::create(['nama' => 'Ancestry B']);
        $category = $this->category('investment');
        $root = $this->entry($category, $a, ['is_parent' => true]);
        $middle = $this->entry($category, $a, ['is_parent' => true, 'parent_id' => $root->id]);
        $leaf = $this->entry($category, $a, ['parent_id' => $middle->id]);
        $otherUnit = $this->entry($category, $b, ['is_parent' => true]);
        $otherCategory = $this->entry($this->category('maintenance'), $a, ['is_parent' => true]);
        $this->rejected(fn () => $leaf->fresh()->update(['parent_id' => $otherUnit->id]), 'parent_id');
        $this->rejected(fn () => $leaf->fresh()->update(['parent_id' => $otherCategory->id]), 'parent_id');
        $this->rejected(fn () => $root->fresh()->update(['parent_id' => $middle->id]), 'parent_id');
        $this->rejected(fn () => $root->fresh()->update(['is_parent' => false]), 'is_parent');
        $this->rejected(fn () => $root->fresh()->update(['unit_nama' => $b->id]), 'unit_nama');
        $this->rejected(fn () => $leaf->fresh()->update(['category_id' => $otherCategory->category_id]), 'category_id');
        $this->rejected(fn () => $leaf->fresh()->forceFill(['origin_id' => 999])->save(), 'category_id');
        $this->assertSame('25.75', $root->fresh()->total);
        $this->assertSame($middle->id, $leaf->fresh()->parent_id);
    }

    public function test_used_category_rules_are_immutable_and_inactive_categories_retain_existing_data(): void
    {
        $category = $this->category('investment'); $unit = Unit::create(['nama' => 'Category lifecycle']);
        $entry = $this->entry($category, $unit);
        foreach (['code' => 'changed', 'calculation_mode' => 'manual', 'direction' => 'income', 'document_prefix' => 'CHG'] as $field => $value) {
            $this->rejected(fn () => $category->fresh()->update([$field => $value]), 'code');
        }
        $category->fresh()->update(['is_active' => false]);
        $this->rejected(fn () => $this->entry($category->fresh(), $unit), 'category_id');
        $entry->update(['uraian' => 'Existing inactive category entry']);
        $this->assertSame('Existing inactive category entry', $entry->fresh()->uraian);
        $this->assertSame('25.75', $entry->fresh()->total);
        $this->rejected(fn () => $category->fresh()->delete(), 'category_id');
    }

    public function test_non_superadmin_cannot_manage_categories_and_forged_unit_is_overridden_on_create(): void
    {
        $a = Unit::create(['nama' => 'Operator A']); $b = Unit::create(['nama' => 'Operator B']);
        $user = User::factory()->create(['unit_id' => $a->id]); $this->actingAs($user);
        $this->get(RkaCategoryResource::getUrl())->assertForbidden();
        $this->assertFalse(RkaCategoryResource::canCreate());
        $category = $this->category('investment');
        Livewire::test(RkaResource::getPages()['index']->getPage())->callAction('create', data: [
            'category_id' => $category->id, 'unit_nama' => $b->id, 'uraian' => 'Forced own unit',
            'is_parent' => false, 'volume' => 1, 'harga_satuan' => '5.25',
        ])->assertHasNoActionErrors();
        $entry = RkaEntry::where('uraian', 'Forced own unit')->firstOrFail();
        $this->assertSame((string) $a->id, (string) $entry->unit_nama);
        $this->assertFalse(RkaCategoryResource::canEdit($category));
    }

    public function test_rollback_refuses_to_discard_active_edits_and_unmodified_import_remains_rollback_safe(): void
    {
        $unit = Unit::create(['nama' => 'Rollback safety']);
        DB::table('rkas')->insert(['uraian' => 'Rollback source', 'unit_nama' => (string) $unit->id,
            'is_parent' => false, 'volume' => '1', 'harga_satuan' => '10', 'total' => '10.00']);
        app(RkaConsolidation::class)->import(); app(RkaConsolidation::class)->assertRollbackSafe();
        $entry = RkaEntry::firstOrFail(); $entry->update(['harga_satuan' => '11.25']);
        try { app(RkaConsolidation::class)->assertRollbackSafe(); $this->fail('Modified active data discarded'); }
        catch (\RuntimeException $exception) { $this->assertStringContainsString('Rollback konsolidasi ditolak', $exception->getMessage()); }
        $this->assertSame('11.25', $entry->fresh()->total);
        $this->assertSame('10.00', DB::table('rkas')->value('total'));
    }

    public function test_category_filtered_export_contains_only_selected_scope_and_keeps_large_decimal_cents(): void
    {
        $unit = Unit::create(['nama' => 'Export unit']); $this->admin();
        $investment = $this->entry($this->category('investment'), $unit, ['uraian' => 'INV-ONLY']);
        $salary = $this->entry($this->category('salary'), $unit, ['uraian' => 'SALARY-ONLY', 'total' => '999999999999999.99']);
        $page = Livewire::test(RkaResource::getPages()['index']->getPage())->set('activeTab', (string) $salary->category_id);
        $page->callAction(\Filament\Actions\Testing\TestAction::make('export')->table(), data: [
            'filename' => 'selected-category', 'format' => 'csv', 'orientation' => 'landscape',
            'columns' => ['category.name', 'uraian', 'total'], 'extra' => [], 'preview' => false,
        ])->assertHasNoActionErrors()->assertFileDownloaded('selected-category.csv');
        $csv = base64_decode($page->effects['download']['content']);
        $this->assertStringContainsString('SALARY-ONLY', $csv); $this->assertStringNotContainsString('INV-ONLY', $csv);
        $this->assertStringContainsString('999999999999999.99', $csv);
        $dataset = app(\App\Services\LegacyExportService::class)->dataset(RkaResource::class, RkaEntry::whereKey($salary->id),
            ['total' => ['label' => 'Total', 'numeric' => true, 'value' => fn ($r) => $r->total]], ['total']);
        $this->assertSame('999999999999999.99', $dataset['rows'][0][0]);
        ob_start();
        try { app(\App\Services\LegacyExportService::class)->download($dataset, ['format' => 'xlsx'])->sendContent(); $bytes = ob_get_contents(); }
        finally { ob_end_clean(); }
        $file = tempnam(sys_get_temp_dir(), 'rka-exact-export-');
        try {
            file_put_contents($file, $bytes); $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
            $this->assertSame('999999999999999.99', $book->getActiveSheet()->getCell('A2')->getValue());
            $this->assertSame('s', $book->getActiveSheet()->getCell('A2')->getDataType()); $book->disconnectWorksheets();
        } finally { unlink($file); }
    }

    public function test_stale_record_cannot_overwrite_newer_inputs_or_delete_a_record_moved_to_another_unit(): void
    {
        $a = Unit::create(['nama' => 'Concurrent A']); $b = Unit::create(['nama' => 'Concurrent B']);
        $entry = $this->entry($this->category('investment'), $a);
        $stale = $entry->fresh();
        $entry->fresh()->update(['volume' => '3']);
        $this->rejected(fn () => $stale->update(['harga_satuan' => '20']), 'uraian');
        $this->assertSame('38.63', $entry->fresh()->total);
        $oldOwner = $entry->fresh();
        $entry->fresh()->update(['unit_nama' => (string) $b->id]);
        $this->rejected(fn () => $oldOwner->delete(), 'uraian');
        $this->assertNotNull($entry->fresh());
    }

    public function test_filament_forms_preserve_large_decimal_amounts_and_four_place_unit_prices_without_float_casts(): void
    {
        $this->admin(); $unit = Unit::create(['nama' => 'Exact form input']);
        Livewire::test(RkaResource::getPages()['index']->getPage())->callAction('create', data: [
            'category_id' => $this->category('salary')->id, 'unit_nama' => $unit->id, 'uraian' => 'Exact manual form',
            'is_parent' => false, 'total' => '999999999999999.99', 'januari' => '999999999999999.99',
        ])->assertHasNoActionErrors();
        $manual = RkaEntry::where('uraian', 'Exact manual form')->firstOrFail();
        $this->assertSame('999999999999999.99', $manual->total);
        $this->assertSame('999999999999999.99', $manual->januari);
        Livewire::test(RkaResource::getPages()['index']->getPage())->callAction('create', data: [
            'category_id' => $this->category('investment')->id, 'unit_nama' => $unit->id, 'uraian' => 'Exact quantity form',
            'is_parent' => false, 'volume' => '1.0001', 'harga_satuan' => '99999999999.9999',
        ])->assertHasNoActionErrors();
        $quantity = RkaEntry::where('uraian', 'Exact quantity form')->firstOrFail();
        $this->assertSame('99999999999.9999', $quantity->harga_satuan);
        $this->assertSame('100010000000.00', $quantity->total);
    }

    public function test_bulk_delete_is_authorized_per_record_and_never_deletes_another_unit(): void
    {
        $a = Unit::create(['nama' => 'Bulk A']); $b = Unit::create(['nama' => 'Bulk B']);
        $category = $this->category('investment');
        $own = $this->entry($category, $a); $foreign = $this->entry($category, $b);
        $this->actingAs(User::factory()->create(['unit_id' => $a->id]));
        Livewire::test(RkaResource::getPages()['index']->getPage())
            ->callTableBulkAction('delete', [$own->id, $foreign->id])->assertHasNoTableBulkActionErrors();
        $this->assertNull($own->fresh()); $this->assertNotNull($foreign->fresh());
    }

    public function test_reimport_never_overwrites_a_changed_archive_or_allows_forged_provenance(): void
    {
        $unit = Unit::create(['nama' => 'Provenance']);
        DB::table('rkas')->insert(['uraian' => 'Original source', 'unit_nama' => (string) $unit->id,
            'is_parent' => false, 'volume' => '1', 'harga_satuan' => '10', 'total' => '10.00']);
        app(RkaConsolidation::class)->import();
        $before = RkaEntry::firstOrFail()->toJson();
        DB::table('rkas')->update(['uraian' => 'Changed source']);
        try { app(RkaConsolidation::class)->import(); $this->fail('Historical source overwritten'); }
        catch (\RuntimeException $exception) { $this->assertStringContainsString('Arsip RKA berubah', $exception->getMessage()); }
        $this->assertSame($before, RkaEntry::firstOrFail()->toJson());
        $forged = new RkaEntry(['category_id' => $this->category('investment')->id,
            'unit_nama' => (string) $unit->id, 'uraian' => 'Forged', 'volume' => '1', 'harga_satuan' => '1']);
        $forged->forceFill(['origin_source' => 'rkas', 'origin_id' => 999]);
        $this->rejected(fn () => $forged->save(), 'category_id');
    }
}
