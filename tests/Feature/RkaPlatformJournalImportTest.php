<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\RkaPlatform\{Organization, Membership, JournalLine};
use App\Services\RkaPlatform\JournalSpreadsheetImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\ValidationException;

class RkaPlatformJournalImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_xlsx_file_is_idempotent_and_does_not_post_to_ledger(): void
    {
        $org = Organization::create(['name' => 'Test organization', 'slug' => 'testing-org']);
        $finance = User::factory()->create();
        Membership::create(['organization_id' => $org->id, 'user_id' => $finance->id, 'roles' => ['finance']]);
        $filePath = tempnam(sys_get_temp_dir(), 'rka-test-');
        $sheet = new Spreadsheet();
        $sheet->getActiveSheet()->fromArray([
            ['line_id','journal_no','date','account_code','debit','credit','posting_status','entry_kind'],
            ['line-1','J-1','2030-01-01','EXP-100',100.00,0.00,'posted','source_expense'],
        ]);
        (new Xlsx($sheet))->save($filePath);
        $sheet->disconnectWorksheets();
        try {
            $upload = new UploadedFile($filePath, 'test.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $service = app(JournalSpreadsheetImporter::class);
            $first = $service->import($org->id, $finance->id, $upload, 'finance-system');
            $second = $service->import($org->id, $finance->id, $upload, 'finance-system');
            $this->assertSame($first->id, $second->id);
            $this->assertSame('staged', $first->status);
            $this->assertSame(1, JournalLine::where('organization_id', $org->id)->count());
            $this->assertSame(1, $first->metadata['unbalanced_count']);
        } finally {
            @unlink($filePath);
        }
    }
    public function test_new_file_with_previously_imported_external_line_id_is_rejected(): void
    {
        $org = Organization::create(['name' => 'Other organization', 'slug' => 'other-organization']);
        $finance = User::factory()->create();
        Membership::create([
            'organization_id' => $org->id, 'user_id' => $finance->id, 'roles' => ['finance'],
        ]);
        $firstPath = tempnam(sys_get_temp_dir(), 'rka-import-');
        $secondPath = tempnam(sys_get_temp_dir(), 'rka-import-');
        $generate = function (string $path, string $amount): void {
            $book = new Spreadsheet();
            $book->getActiveSheet()->fromArray([
                ['line_id', 'journal_no', 'date', 'account_code', 'debit', 'credit'],
                ['external-001', 'J-2030', '2030-01-01', 'ACC-1', $amount, 0],
            ]);
            (new Xlsx($book))->save($path);
            $book->disconnectWorksheets();
        };
        try {
            $generate($firstPath, '100.00');
            $generate($secondPath, '200.00');
            $service = app(JournalSpreadsheetImporter::class);
            $first = new UploadedFile($firstPath, 'first.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $second = new UploadedFile($secondPath, 'second.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $service->import($org->id, $finance->id, $first, 'finance');
            try {
                $service->import($org->id, $finance->id, $second, 'finance');
                $this->fail('Previously imported line identity must not be duplicated.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
            $this->assertSame(1, JournalLine::where('organization_id', $org->id)->count());
        } finally {
            @unlink($firstPath);
            @unlink($secondPath);
        }
    }

}
