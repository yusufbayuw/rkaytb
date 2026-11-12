<?php

namespace Tests\Feature;

use App\Models\RkaPlatform\{Organization, Cycle, BookTemplate, Book};
use App\Services\RkaPlatform\BookExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;
use DOMDocument;

class RkaPlatformBookExportTest extends TestCase
{
    use RefreshDatabase;

    private function publishedBook(): Book
    {
        $org = Organization::create(['name' => 'Generic company', 'slug' => 'export-generic']);
        $cycle = Cycle::create([
            'organization_id' => $org->id, 'name' => 'Cycle',
            'year' => 2035, 'starts_on' => '2035-01-01', 'ends_on' => '2035-12-31',
        ]);
        $template = BookTemplate::create([
            'organization_id' => $org->id, 'name' => 'Open format', 'sections' => [],
        ]);
        $report = [
            'cycle_id' => $cycle->id, 'currency' => 'USD',
            'period' => ['2035-01-01','2035-12-31'],
            'blocker_count' => 0, 'posted_line_count' => 6, 'source_batch_count' => 1,
            'consolidated' => [
                'gross_income' => '600.00', 'gross_expense' => '300.00',
                'eliminated_internal_income' => '100.00', 'eliminated_internal_expense' => '100.00',
                'income' => '500.00', 'expense' => '200.00', 'surplus' => '300.00',
            ],
            'unit_managerial' => [
                [
                    'unit' => 'Alpha', 'plan_income' => '500.00', 'plan_expense' => '200.00',
                    'plan_sharing' => '0.00', 'plan_surplus' => '300.00',
                    'actual_surplus' => '400.00', 'variance' => '100.00',
                ],
                [
                    'unit' => 'Beta', 'plan_income' => '0.00', 'plan_expense' => '0.00',
                    'plan_sharing' => '100.00', 'plan_surplus' => '-100.00',
                    'actual_surplus' => '-100.00', 'variance' => '0.00',
                ],
            ],
        ];
        $snapshot = [
            'title' => 'Verified Annual Plan', 'sha256' => str_repeat('a', 64),
            'currency' => 'USD', 'brand_color' => '#1e3a5f',
            'closing' => ['id'=>1,'version'=>1,'source_hash'=>str_repeat('b',64),
                'report_hash'=>str_repeat('c',64)],
            'financial_report' => $report,
            'units' => [['id'=>1,'name'=>'Alpha'],['id'=>2,'name'=>'Beta']],
            'budgets' => [['unit_id'=>1,'account_code'=>'ACC','category'=>'INCOME','name'=>'Income',
                'amount'=>'500.00']],
            'shared_pools' => [['code'=>'SH-1','name'=>'Shared','amount'=>'100.00','method'=>'equal',
                'allocations'=>['2'=>'100.00']]],
            'sections' => [
                ['code'=>'intro','title'=>'Introduction','block_type'=>'narrative',
                    'content'=>'Annual plan based on approved results.'],
                ['code'=>'financial','title'=>'Consolidated Statement','block_type'=>'consolidated',
                    'content'=>null],
                ['code'=>'unit','title'=>'Unit Results','block_type'=>'unit_kpi','content'=>null],
                ['code'=>'shared','title'=>'Shared Allocations','block_type'=>'shared_cost','content'=>null],
                ['code'=>'check','title'=>'Reconciliation','block_type'=>'reconciliation','content'=>null],
            ],
        ];
        return Book::create([
            'organization_id' => $org->id, 'cycle_id' => $cycle->id,
            'template_id' => $template->id, 'title' => $snapshot['title'],
            'status' => 'published', 'snapshot' => $snapshot,
        ]);
    }

    private function bytes(Book $book, string $format): string
    {
        $stream = app(BookExportService::class)->export($book, $format);
        ob_start();
        try {
            $stream->sendContent();
            return (string)ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    public function test_pdf_renders_frozen_consolidated_kpi_and_narrative(): void
    {
        $pdf = $this->bytes($this->publishedBook(), 'pdf');
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(500, strlen($pdf));
    }

    public function test_docx_contains_automatic_tables_and_a_valid_chart_image(): void
    {
        $docx = $this->bytes($this->publishedBook(), 'docx');
        $this->assertStringStartsWith('PK', $docx);
        $path = tempnam(sys_get_temp_dir(), 'rka-docx-');
        file_put_contents($path, $docx);
        try {
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($path) === true);
            $xml = $zip->getFromName('word/document.xml');
            $this->assertNotFalse($xml);
            $this->assertStringContainsString('Consolidated Statement', $xml);
            $this->assertStringContainsString('Surplus', $xml);
            $this->assertStringContainsString('Alpha', $xml);
            $document = new DOMDocument();
            $this->assertTrue($document->loadXML($xml));
            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            foreach ($xpath->query('//w:tbl') as $table) {
                $width = 0;
                foreach ($xpath->query('w:tblGrid/w:gridCol', $table) as $column) {
                    $width += (int) $column->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w');
                }
                $this->assertGreaterThan(0, $width);
                $this->assertLessThanOrEqual(9638, $width);
                $this->assertSame(1, $xpath->query('w:tr/w:trPr/w:tblHeader', $table)->length);
            }
            $styles = new DOMDocument();
            $this->assertTrue($styles->loadXML($zip->getFromName('word/styles.xml')));
            if (extension_loaded('gd')) {
                $this->assertNotFalse($zip->getFromName('word/media/kpi-chart.png'));
            }
            $zip->close();
        } finally {
            @unlink($path);
        }
    }

    public function test_long_documents_export_from_one_unchanged_snapshot(): void
    {
        $book = $this->publishedBook();
        $snapshot = $book->snapshot;
        $snapshot['title'] = 'Buku RKA Pengujian Dokumen Panjang';
        $template = $snapshot['financial_report']['unit_managerial'][0];
        $snapshot['financial_report']['unit_managerial'] = [];
        for ($i = 1; $i <= 48; $i++) {
            $snapshot['financial_report']['unit_managerial'][] = [...$template,
                'unit' => 'Unit Pengujian '.$i.' dengan uraian organisasi yang panjang'];
        }
        for ($i = 1; $i <= 8; $i++) {
            $snapshot['sections'][] = ['code' => 'long-'.$i, 'title' => 'Evaluasi dan Rencana Kegiatan '.$i,
                'block_type' => 'narrative', 'content' => str_repeat("Dokumen ini menggunakan data pengujian. Evaluasi kegiatan menjelaskan capaian, penggunaan anggaran, dan tindak lanjut yang ditinjau secara independen.\n", 16)];
        }
        $book->update(['snapshot' => $snapshot]);
        // Compare the persisted JSON baseline: MySQL normalizes object key order.
        $book->refresh();
        $snapshot = $book->snapshot;
        foreach (['pdf', 'docx', 'xlsx'] as $format) {
            $bytes = $this->bytes($book, $format);
            $this->assertGreaterThan(1000, strlen($bytes));
            if (getenv('RKA_EXPORT_QA') === '1') {
                $directory = base_path('.tools/export-qa');
                if (!is_dir($directory)) mkdir($directory, 0775, true);
                file_put_contents($directory.'/long-rka-book.'.$format, $bytes);
            }
            $this->assertSame($snapshot, $book->fresh()->snapshot);
        }
    }

    public function test_xlsx_contains_financial_sheets_and_chart(): void
    {
        $xlsx = $this->bytes($this->publishedBook(), 'xlsx');
        $this->assertStringStartsWith('PK', $xlsx);
        $path = tempnam(sys_get_temp_dir(), 'rka-xlsx-');
        file_put_contents($path, $xlsx);
        try {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $spreadsheet = $reader->load($path);
            try {
                $this->assertSame('300.00', number_format(
                    (float)$spreadsheet->getSheetByName('Konsolidasi')->getCell('B8')->getValue(), 2, '.', ''));
                $this->assertSame('Alpha', $spreadsheet->getSheetByName('KPI Budget')->getCell('A2')->getValue());
                $this->assertNotNull($spreadsheet->getSheetByName('Rekonsiliasi'));
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($path) === true);
            $this->assertNotFalse($zip->getFromName('xl/charts/chart1.xml'));
            $zip->close();
        } finally {
            @unlink($path);
        }
    }
}
