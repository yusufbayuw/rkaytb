<?php

namespace Tests\Feature;

use App\Filament\Resources\RkaResource;
use App\Models\{Rka, Unit, User};
use App\Services\{LegacyExportService, PermissionSpreadsheetImporter};
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class LegacyImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('superadmin', 'web'));
        $this->actingAs($user);
    }

    public function test_permission_import_maps_columns_and_reports_duplicates_and_invalid_rows(): void
    {
        $this->admin();
        $file = UploadedFile::fake()->createWithContent('permissions.csv', "Description,Permission\nTest,read:unit\nAgain,read:unit\nMissing,\nOther,write:unit\n");
        $importer = app(PermissionSpreadsheetImporter::class);
        $this->assertSame(['Description', 'Permission'], array_values($importer->headers($file)));
        $result = $importer->import($file, 1);
        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['duplicates']);
        $this->assertSame(4, $result['errors'][0]['row']);
        $this->assertSame(2, Permission::count());
        $this->assertSame(0, $importer->import($file, 1)['created']);

        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $book->getActiveSheet()->fromArray([['name'], ['read:first-sheet']]);
        $book->createSheet()->fromArray([['name'], ['read:unchecked-sheet']]);
        $book->setActiveSheetIndex(1);
        $path = tempnam(sys_get_temp_dir(), 'permission-import-');
        try {
            IOFactory::createWriter($book, 'Xlsx')->save($path);
            $xlsx = new UploadedFile($path, 'permissions.xlsx', null, null, true);
            $this->assertSame(['name'], array_values($importer->headers($xlsx)));
            $this->assertSame(1, $importer->import($xlsx, 0)['created']);
            $this->assertTrue(Permission::where('name', 'read:first-sheet')->exists());
            $this->assertFalse(Permission::where('name', 'read:unchecked-sheet')->exists());
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
    }

    public function test_permission_import_rejects_unauthorized_users(): void
    {
        $this->actingAs(User::factory()->create());
        $this->expectException(AuthorizationException::class);
        app(PermissionSpreadsheetImporter::class)->import(UploadedFile::fake()->createWithContent('p.csv', "name\nread:unit"), 0);
    }

    public function test_permission_import_rejects_invalid_workbooks(): void
    {
        $this->admin();
        $this->expectException(ValidationException::class);
        app(PermissionSpreadsheetImporter::class)->import(UploadedFile::fake()->createWithContent('p.xlsx', 'not a workbook'), 0);
    }

    private function dataset(): array
    {
        $a = Unit::create(['nama' => 'A']);
        $b = Unit::create(['nama' => 'B']);
        $this->actingAs(User::factory()->create(['unit_id' => $a->id]));
        Rka::create(['uraian' => '=HYPERLINK("https://example.test")', 'nomor_akun' => '00123', 'unit_nama' => (string) $a->id,
            'is_parent' => false, 'volume' => 2, 'harga_satuan' => 12.5]);
        Rka::create(['uraian' => 'Private other unit', 'unit_nama' => (string) $b->id, 'is_parent' => false, 'volume' => 1, 'harga_satuan' => 100]);
        $definitions = [
            'uraian' => ['label' => 'Uraian', 'value' => fn ($record) => $record->uraian],
            'nomor_akun' => ['label' => 'Akun', 'value' => fn ($record) => $record->nomor_akun],
            'total' => ['label' => 'Total', 'value' => fn ($record) => $record->total, 'numeric' => true],
        ];
        return app(LegacyExportService::class)->dataset(RkaResource::class, RkaResource::getEloquentQuery(), $definitions, array_keys($definitions));
    }

    private function bytes($response): string
    {
        ob_start();
        try { $response->sendContent(); return (string) ob_get_contents(); }
        finally { ob_end_clean(); }
    }

    public function test_legacy_export_preserves_scope_account_codes_and_numeric_values_in_all_formats(): void
    {
        $dataset = $this->dataset();
        $this->assertCount(1, $dataset['rows']);
        $this->assertSame('00123', $dataset['rows'][0][1]);
        $this->assertSame(25.0, $dataset['rows'][0][2]);
        $writes = [];
        DB::listen(function ($query) use (&$writes) { if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) $writes[] = $query->sql; });
        $service = app(LegacyExportService::class);
        $csv = $this->bytes($service->download($dataset, ['format' => 'csv']));
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('Private other unit', $csv);
        $pdf = $this->bytes($service->download($dataset, ['format' => 'pdf']));
        $this->assertStringStartsWith('%PDF-', $pdf);
        $xlsx = $this->bytes($service->download($dataset, ['format' => 'xlsx']));
        $file = tempnam(sys_get_temp_dir(), 'rka-export-');
        file_put_contents($file, $xlsx);
        try {
            $book = IOFactory::load($file);
            $this->assertSame('00123', $book->getActiveSheet()->getCell('B2')->getValue());
            $this->assertEquals(25, $book->getActiveSheet()->getCell('C2')->getValue());
            $this->assertSame('s', $book->getActiveSheet()->getCell('A2')->getDataType());
            $book->disconnectWorksheets();
        } finally { unlink($file); }
        $this->assertSame([], $writes);

        $this->withoutVite();
        $component = \Livewire\Livewire::test(RkaResource::getPages()['index']->getPage())
            ->callAction(\Filament\Actions\Testing\TestAction::make('export')->table(), data: [
                'filename' => 'rka-labelled', 'format' => 'csv', 'orientation' => 'portrait',
                'columns' => ['unit_nama', 'nomor_akun', 'total'], 'extra' => [], 'preview' => false,
            ])->assertHasNoActionErrors()->assertFileDownloaded('rka-labelled.csv');
        $download = base64_decode($component->effects['download']['content']);
        $this->assertStringContainsString('A,00123,25', $download);
        $this->assertStringNotContainsString('Private other unit', $download);
    }

    public function test_export_rejects_unknown_columns(): void
    {
        $this->admin();
        $this->expectException(ValidationException::class);
        app(LegacyExportService::class)->dataset(RkaResource::class, Rka::query(), [], ['password']);
    }
}
