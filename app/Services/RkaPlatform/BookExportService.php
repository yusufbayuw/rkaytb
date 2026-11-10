<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\Book;
use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;
use RuntimeException;

class BookExportService
{
    public function export(Book $book, string $format): StreamedResponse
    {
        if ($book->status !== 'published' || !is_array($book->snapshot)) {
            throw new RuntimeException('Hanya snapshot buku terbit yang dapat diekspor.');
        }
        $snapshot = $book->snapshot;
        $name = 'rka-book-'.$book->id.'-'.substr($snapshot['sha256'], 0, 10).'.'.$format;
        if ($format === 'pdf') {
            if (!class_exists(Dompdf::class)) throw new RuntimeException('PDF renderer belum tersedia.');
            $pdf = new Dompdf(['isRemoteEnabled' => false, 'isPhpEnabled' => false]);
            $pdf->loadHtml($this->html($snapshot), 'UTF-8');
            $pdf->setPaper('a4');
            $pdf->render();
            return response()->streamDownload(fn () => print($pdf->output()), $name,
                ['Content-Type' => 'application/pdf']);
        }
        if ($format === 'xlsx') {
            if (!class_exists(Spreadsheet::class)) throw new RuntimeException('XLSX renderer belum tersedia.');
            return response()->streamDownload(function () use ($snapshot) {
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet(); $sheet->setTitle('RKA');
                $sheet->fromArray(['Unit ID','Akun','Kategori','Uraian','Anggaran'], null, 'A1');
                $row = 2;
                foreach ($snapshot['budgets'] as $item) {
                    $sheet->setCellValue('A'.$row, $item['unit_id']);
                    foreach (['B' => 'account_code','C' => 'category','D' => 'name'] as $col => $key) {
                        $sheet->setCellValueExplicit($col.$row, (string)($item[$key] ?? ''), DataType::TYPE_STRING);
                    }
                    $sheet->setCellValue('E'.$row, (float)$item['amount']);
                    $row++;
                }
                $sheet->getStyle('A1:E1')->getFont()->setBold(true);
                $sheet->getColumnDimension('D')->setWidth(45);
                $sheet->getStyle('E2:E'.$row)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00);-');
                $share = $spreadsheet->createSheet(); $share->setTitle('Shared Cost');
                $share->fromArray(['Cost Pool','Nominal','Metode','Alokasi (JSON)'], null, 'A1');
                foreach ($snapshot['shared_pools'] as $i => $pool) {
                    $rowIndex = $i+2;
                    $share->setCellValueExplicit('A'.$rowIndex, (string)$pool['code'], DataType::TYPE_STRING);
                    $share->setCellValue('B'.$rowIndex, (float)$pool['amount']);
                    $share->setCellValueExplicit('C'.$rowIndex, (string)$pool['method'], DataType::TYPE_STRING);
                    $share->setCellValueExplicit('D'.$rowIndex,
                        json_encode($pool['allocations'], JSON_UNESCAPED_UNICODE), DataType::TYPE_STRING);
                }
                (new Xlsx($spreadsheet))->save('php://output');
                $spreadsheet->disconnectWorksheets();
            }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }
        if ($format === 'docx') {
            if (!class_exists(ZipArchive::class)) throw new RuntimeException('Ekstensi ZIP belum tersedia.');
            $temp = tempnam(sys_get_temp_dir(), 'rka-doc-');
            $this->docx($temp, $snapshot);
            return response()->streamDownload(function () use ($temp) {
                try { readfile($temp); } finally { @unlink($temp); }
            }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
        }
        throw new RuntimeException('Format belum didukung.');
    }

    private function html(array $snapshot): string
    {
        $safe = fn ($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<html><head><meta charset="UTF-8"><style>body{font-family:DejaVu Sans;font-size:11pt;line-height:1.45}h1,h2{color:#24344a}h1{text-align:center;margin:3em 0}section{page-break-inside:auto}p{white-space:pre-wrap}</style></head><body>';
        $html .= '<h1>'.$safe($snapshot['title']).'</h1>';
        foreach ($snapshot['sections'] as $section) {
            $html .= '<section><h2>'.$safe($section['title']).'</h2><p>'.nl2br($safe($section['content'])).'</p></section>';
        }
        return $html.'</body></html>';
    }

    private function docx(string $target, array $snapshot): void
    {
        $safe = fn ($s) => htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $paragraph = fn ($value, $style = '') => '<w:p><w:pPr>'.$style.'</w:pPr><w:r><w:t xml:space="preserve">'.$safe($value).'</w:t></w:r></w:p>';
        $xml = $paragraph($snapshot['title'], '<w:jc w:val="center"/>');
        foreach ($snapshot['sections'] as $section) {
            $xml .= $paragraph($section['title'], '<w:pStyle w:val="Heading1"/>');
            foreach (preg_split('/\R/u', $section['content'] ?: '') as $line) $xml .= $paragraph($line);
        }
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'.
            '<w:body>'.$xml.'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'.
            '<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr></w:body></w:document>';
        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuat DOCX.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'.
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'.
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'.
            '<Default Extension="xml" ContentType="application/xml"/>'.
            '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'.
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', $document);
        $zip->close();
    }
}
