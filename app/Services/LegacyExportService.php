<?php

namespace App\Services;

use Dompdf\Dompdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LegacyExportService
{
    public function dataset(string $resource, Builder $query, array $definitions, array $selected, array $extra = [], ?int $limit = null): array
    {
        abort_unless($resource::canViewAny(), 403);
        if (!$selected || array_diff($selected, array_keys($definitions)) || count($extra) > 10) {
            throw ValidationException::withMessages(['columns' => 'Pilih kolom ekspor yang tersedia.']);
        }
        $headers = array_map(fn ($key) => (string) $definitions[$key]['label'], $selected);
        foreach ($extra as $column) {
            if (mb_strlen($column['title'] ?? '') > 100 || mb_strlen($column['value'] ?? '') > 255) {
                throw ValidationException::withMessages(['extra' => 'Kolom tambahan terlalu panjang.']);
            }
            $headers[] = (string) ($column['title'] ?? '');
        }
        $scoped = clone $query;
        if ($limit !== null) $scoped->limit($limit);
        $rows = [];
        foreach ($scoped->get() as $record) {
            abort_unless($resource::canView($record), 403);
            $row = [];
            foreach ($selected as $key) {
                $value = ($definitions[$key]['value'])($record);
                // Excel numbers retain at most 15 significant digits. Keep larger
                // historical amounts as text so their decimal cents survive export.
                if (($definitions[$key]['numeric'] ?? false) && is_numeric($value)
                    && strlen(ltrim(str_replace(['.', '-', '+'], '', (string) $value), '0')) <= 15) $value = (float) $value;
                $row[] = is_scalar($value) || $value === null ? $value : (string) $value;
            }
            foreach ($extra as $column) $row[] = (string) ($column['value'] ?? '');
            $rows[] = $row;
        }
        return compact('headers', 'rows');
    }

    public function html(array $dataset, string $title, string $orientation = 'portrait'): string
    {
        return view('exports.legacy-table', [
            'dataset' => $dataset, 'title' => $title,
            'orientation' => $orientation === 'landscape' ? 'landscape' : 'portrait',
        ])->render();
    }

    public function download(array $dataset, array $options): StreamedResponse
    {
        $format = $options['format'] ?? 'xlsx';
        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf'], true), 422);
        $filename = Str::slug(($options['prefix'] ?? '').'-'.($options['filename'] ?? 'rka-export')) ?: 'rka-export';
        $filename = Str::limit($filename, 120, '').'.'.$format;
        if ($format === 'pdf') {
            $pdf = new Dompdf(['isRemoteEnabled' => false, 'isPhpEnabled' => false]);
            $pdf->loadHtml($this->html($dataset, $options['filename'] ?? 'RKA', $options['orientation'] ?? 'portrait'), 'UTF-8');
            $pdf->setPaper('a4', ($options['orientation'] ?? '') === 'landscape' ? 'landscape' : 'portrait');
            $pdf->render();
            return response()->streamDownload(fn () => print($pdf->output()), $filename, ['Content-Type' => 'application/pdf']);
        }
        if ($format === 'csv') {
            return response()->streamDownload(function () use ($dataset) {
                $stream = fopen('php://output', 'wb');
                fwrite($stream, "\xEF\xBB\xBF");
                foreach ([$dataset['headers'], ...$dataset['rows']] as $row) {
                    $safe = array_map(fn ($value) => is_string($value) && preg_match('/^[=+@\-\t\r]/u', $value) && !is_numeric($value) ? "'".$value : $value, $row);
                    fputcsv($stream, $safe, ',', '"', '');
                }
                fclose($stream);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        return response()->streamDownload(function () use ($dataset) {
            $workbook = new Spreadsheet();
            try {
                $sheet = $workbook->getActiveSheet();
                foreach ([$dataset['headers'], ...$dataset['rows']] as $r => $row) {
                    foreach ($row as $c => $value) {
                        $sheet->setCellValueExplicit([$c + 1, $r + 1], $value ?? '',
                            $r > 0 && (is_int($value) || is_float($value)) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                    }
                }
                $sheet->freezePane('A2');
                $sheet->getStyle('1:1')->getFont()->setBold(true);
                (new Xlsx($workbook))->save('php://output');
            } finally {
                $workbook->disconnectWorksheets();
            }
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
