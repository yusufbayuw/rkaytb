<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\Book;
use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Chart\{Chart, DataSeries, DataSeriesValues, Legend, PlotArea, Title};
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;
use RuntimeException;

class BookExportService
{
    private function blocks(): BookAutoContentService
    {
        return app(BookAutoContentService::class);
    }

    public function export(Book $book, string $format): StreamedResponse
    {
        if ($book->status !== 'published' || !is_array($book->snapshot)) {
            throw new RuntimeException('Hanya snapshot buku terbit yang dapat diekspor.');
        }
        if (!in_array($format, ['docx','pdf','xlsx'], true)) {
            throw new RuntimeException('Format belum didukung.');
        }
        $snapshot = $book->snapshot;
        $name = 'rka-book-'.$book->id.'-'.substr((string)$snapshot['sha256'], 0, 10).'.'.$format;
        if ($format === 'pdf') {
            if (!class_exists(Dompdf::class)) throw new RuntimeException('PDF renderer tidak tersedia.');
            $pdf = new Dompdf(['isRemoteEnabled' => false, 'isPhpEnabled' => false]);
            $pdf->loadHtml($this->html($snapshot), 'UTF-8');
            $pdf->setPaper('a4');
            $pdf->render();
            return response()->streamDownload(fn () => print($pdf->output()), $name,
                ['Content-Type' => 'application/pdf']);
        }
        if ($format === 'xlsx') {
            return response()->streamDownload(fn () => $this->xlsx($snapshot), $name,
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('Ekstensi ZIP tidak tersedia.');
        $temp = tempnam(sys_get_temp_dir(), 'rka-doc-');
        try {
            $this->docx($temp, $snapshot);
        } catch (\Throwable $e) {
            @unlink($temp);
            throw $e;
        }
        return response()->streamDownload(function () use ($temp) {
            try { readfile($temp); } finally { @unlink($temp); }
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
    }

    private function esc(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function htmlTable(array $table): string
    {
        $html = '<table><thead><tr>';
        foreach ($table['headers'] as $heading) $html .= '<th>'.$this->esc($heading).'</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($table['rows'] as $row) {
            $html .= '<tr>';
            foreach ($row as $value) $html .= '<td>'.$this->esc($value).'</td>';
            $html .= '</tr>';
        }
        return $html.'</tbody></table>';
    }

    private function html(array $snapshot): string
    {
        $color = (string)($snapshot['brand_color'] ?? '#24344a');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#24344a';
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'.
            '@page{margin:22mm 18mm}body{font-family:DejaVu Sans;font-size:9.5pt;line-height:1.5}'.
            'h1,h2{color:'.$color.'}h1{text-align:center;margin:70mm 0 8mm}'.
            'h2{margin-top:11mm;page-break-after:avoid}p{white-space:pre-wrap}'.
            'table{border-collapse:collapse;width:100%;margin:7mm 0;font-size:8pt}'.
            'th{background-color:#e6edf4}th,td{border:1px solid #cbd5e1;padding:5px 6px;text-align:left}'.
            'tr{page-break-inside:avoid}.small{color:#64748b;font-size:8pt}.chart{width:100%;height:auto}'.
            '</style></head><body>';
        $html .= '<h1>'.$this->esc($snapshot['title']).'</h1>';
        $html .= '<p class="small" style="text-align:center">Mata uang: '.$this->esc($snapshot['currency'] ?? '').'</p>';
        if (isset($snapshot['closing'])) {
            $html .= '<p class="small" style="text-align:center">Closing v'.$this->esc($snapshot['closing']['version'] ?? '').
                ' · Hash laporan '.$this->esc(substr($snapshot['closing']['report_hash'] ?? '', 0, 16)).'</p>';
        }
        $hasChart = false;
        foreach ($snapshot['sections'] as $section) {
            $html .= '<section><h2>'.$this->esc($section['title']).'</h2>';
            $type = $section['block_type'] ?? 'narrative';
            if ($type === 'narrative') {
                $html .= '<p>'.nl2br($this->esc($section['content'] ?? '')).'</p>';
            } else {
                $table = $this->blocks()->table($type, $snapshot);
                if ($table) $html .= $this->htmlTable($table);
                if ($type === 'unit_kpi' && !$hasChart) {
                    $chart = $this->blocks()->chartPng($snapshot);
                    if ($chart) {
                        $html .= '<p><img class="chart" src="data:image/png;base64,'.base64_encode($chart).'" alt="Grafik perbandingan surplus" /></p>';
                        $hasChart = true;
                    }
                }
            }
            $html .= '</section>';
        }
        return $html.'</body></html>';
    }

    private function xlsx(array $snapshot): void
    {
        $book = new Spreadsheet();
        try {
            $sheet = $book->getActiveSheet();
            $sheet->setTitle('RKA');
            $this->fill($sheet, [
                'headers' => ['Unit ID','Akun','Kategori','Uraian','Anggaran'],
                'rows' => array_map(fn ($b) => [
                    $b['unit_id'] ?? '', $b['account_code'] ?? '', $b['category'] ?? '',
                    $b['name'] ?? '', $b['amount'] ?? '0.00',
                ], $snapshot['budgets'] ?? []),
            ], [4]);
            $share = $book->createSheet(); $share->setTitle('Sharing Budget');
            $names = [];
            foreach ($snapshot['units'] ?? [] as $unit) $names[(string)$unit['id']] = $unit['name'];
            $shareRows = [];
            foreach (($snapshot['shared_pools'] ?? []) as $pool) {
                foreach (($pool['allocations'] ?? []) as $unitId => $value) {
                    $shareRows[] = [$pool['code'], $pool['name'], $pool['method'],
                        $names[(string)$unitId] ?? (string)$unitId, $value];
                }
            }
            $this->fill($share, ['headers' => ['Kode','Pool','Metode','Unit','Alokasi'], 'rows' => $shareRows], [4]);

            $financial = $snapshot['financial_report'] ?? null;
            if ($financial) {
                $con = $book->createSheet(); $con->setTitle('Konsolidasi');
                $this->fill($con, $this->blocks()->table('consolidated', $snapshot), [1]);
                $kpi = $book->createSheet(); $kpi->setTitle('KPI Budget');
                $this->fill($kpi, $this->blocks()->table('unit_kpi', $snapshot), [1,2,3,4,5,6]);
                $recon = $book->createSheet(); $recon->setTitle('Rekonsiliasi');
                $this->fill($recon, $this->blocks()->table('reconciliation', $snapshot), []);
                $this->addKpiChart($kpi, count($financial['unit_managerial'] ?? []));
            }
            $narrative = $book->createSheet(); $narrative->setTitle('Narasi');
            $narrative->fromArray(['Bagian','Jenis','Isi'], null, 'A1');
            foreach ($snapshot['sections'] ?? [] as $i => $section) {
                $line = $i + 2;
                foreach (['A' => $section['title'], 'B' => $section['block_type'] ?? 'narrative',
                    'C' => $section['content'] ?? ''] as $column => $value) {
                    $narrative->setCellValueExplicit($column.$line, (string)$value, DataType::TYPE_STRING);
                }
            }
            $narrative->getColumnDimension('A')->setWidth(40);
            $narrative->getColumnDimension('C')->setWidth(75);
            $writer = new Xlsx($book);
            $writer->setIncludeCharts(true);
            $writer->save('php://output');
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function fill(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $table, array $moneyColumns): void
    {
        $columns = count($table['headers']);
        $sheet->fromArray([$table['headers']], null, 'A1');
        $sheet->getStyle('A1:'.\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columns).'1')
            ->getFont()->setBold(true);
        foreach ($table['rows'] as $i => $row) {
            $rowNumber = $i + 2;
            foreach ($row as $j => $value) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($j + 1).$rowNumber;
                if (in_array($j, $moneyColumns, true) && preg_match('/^-?\d+(?:\.\d{1,2})?$/', (string)$value)) {
                    $sheet->setCellValue($cell, (float)$value);
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00);-');
                } else {
                    $sheet->setCellValueExplicit($cell, (string)$value, DataType::TYPE_STRING);
                }
            }
        }
        foreach (range(1, min($columns, 8)) as $column) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column))->setWidth($column === 1 ? 33 : 24);
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columns).max(2,count($table['rows']) + 1));
    }

    private function addKpiChart(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $count): void
    {
        if ($count < 1 || !class_exists(Chart::class)) return;
        $end = $count + 1;
        $labels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'KPI Budget'!\$E\$1", null, 1),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'KPI Budget'!\$F\$1", null, 1),
        ];
        $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING,
            "'KPI Budget'!\$A\$2:\$A\$$end", null, $count)];
        $values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER,
                "'KPI Budget'!\$E\$2:\$E\$$end", null, $count),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER,
                "'KPI Budget'!\$F\$2:\$F\$$end", null, $count),
        ];
        $series = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED,
            [0,1], $labels, $categories, $values);
        $series->setPlotDirection(DataSeries::DIRECTION_COL);
        $chart = new Chart('kpi-surplus', new Title('Surplus Rencana dan Aktual'),
            new Legend(Legend::POSITION_BOTTOM), new PlotArea(null, [$series]), true);
        $chart->setTopLeftPosition('I3');
        $chart->setBottomRightPosition('S22');
        $sheet->addChart($chart);
    }

    private function wordParagraph(mixed $value, string $style = ''): string
    {
        return '<w:p><w:pPr>'.$style.'</w:pPr><w:r><w:t xml:space="preserve">'.
            htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</w:t></w:r></w:p>';
    }

    private function wordTable(array $table): string
    {
        $rows = array_merge([$table['headers']], $table['rows']);
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/>'.
            '<w:tblBorders><w:bottom w:val="single" w:sz="4"/>'.
            '<w:top w:val="single" w:sz="4"/><w:insideH w:val="single" w:sz="4"/>'.
            '</w:tblBorders></w:tblPr>';
        foreach ($rows as $i => $row) {
            $xml .= '<w:tr>';
            foreach ($row as $value) {
                $xml .= '<w:tc><w:tcPr><w:tcW w:w="1800" w:type="dxa"/></w:tcPr>'.
                    $this->wordParagraph($value, $i === 0 ? '<w:rPr><w:b/></w:rPr>' : '').'</w:tc>';
            }
            $xml .= '</w:tr>';
        }
        return $xml.'</w:tbl>';
    }

    private function wordChartDrawing(): string
    {
        return '<w:p><w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'.
            '<wp:extent cx="5486400" cy="2743200"/><wp:docPr id="1" name="Grafik KPI"/>'.
            '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'.
            '<pic:pic><pic:nvPicPr><pic:cNvPr id="1" name="kpi-chart.png"/><pic:cNvPicPr/></pic:nvPicPr>'.
            '<pic:blipFill><a:blip r:embed="rIdKpi"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'.
            '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="5486400" cy="2743200"/></a:xfrm>'.
            '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic>'.
            '</a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
    }

    private function docx(string $target, array $snapshot): void
    {
        $xml = $this->wordParagraph($snapshot['title'], '<w:jc w:val="center"/>');
        $xml .= $this->wordParagraph('Mata uang: '.($snapshot['currency'] ?? ''));
        if (isset($snapshot['closing'])) {
            $xml .= $this->wordParagraph('Closing v'.($snapshot['closing']['version'] ?? '').' · '.
                substr($snapshot['closing']['report_hash'] ?? '', 0, 18));
        }
        $chart = null;
        foreach ($snapshot['sections'] as $section) {
            $xml .= $this->wordParagraph($section['title'], '<w:pStyle w:val="Heading1"/>');
            $type = $section['block_type'] ?? 'narrative';
            if ($type === 'narrative') {
                foreach (preg_split('/\R/u', $section['content'] ?? '') as $line) $xml .= $this->wordParagraph($line);
            } else {
                $table = $this->blocks()->table($type, $snapshot);
                if ($table) $xml .= $this->wordTable($table);
                if ($type === 'unit_kpi' && !$chart) {
                    $chart = $this->blocks()->chartPng($snapshot);
                    if ($chart) $xml .= $this->wordChartDrawing();
                }
            }
        }
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'.
            ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'.
            ' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"'.
            ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'.
            ' xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'.
            '<w:body>'.$xml.'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'.
            '<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/>'.
            '</w:sectPr></w:body></w:document>';
        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuat DOCX.');
        }
        try {
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'.
                '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'.
                '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'.
                '<Default Extension="xml" ContentType="application/xml"/>'.
                '<Default Extension="png" ContentType="image/png"/>'.
                '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'.
                '</Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'.
                '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
                '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'.
                '</Relationships>');
            $zip->addFromString('word/document.xml', $document);
            if ($chart) {
                $zip->addFromString('word/media/kpi-chart.png', $chart);
                $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'.
                    '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
                    '<Relationship Id="rIdKpi" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/kpi-chart.png"/>'.
                    '</Relationships>');
            }
        } finally {
            $zip->close();
        }
    }
}
