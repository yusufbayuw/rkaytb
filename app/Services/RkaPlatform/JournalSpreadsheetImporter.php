<?php

namespace App\Services\RkaPlatform;

use App\Models\RkaPlatform\{JournalBatch, JournalLine, Unit};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class JournalSpreadsheetImporter
{
    public function __construct(private TenantAccess $access, private AuditTrail $audit) {}

    // Header mapping is configurable per institution; source workbook examples are NOT schema definitions.
    public function import(int $orgId, int $userId, UploadedFile $file, string $source, array $mapping = []): JournalBatch
    {
        $this->access->requireRole($orgId, $userId, ['owner', 'admin', 'finance']);
        if (!preg_match('/^[a-zA-Z0-9_.-]{2,60}$/', $source) || strtolower($file->getClientOriginalExtension()) !== 'xlsx' ||
            $file->getSize() > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['journalFile' => 'Sumber atau berkas XLSX tidak valid (maks 10 MB).']);
        }
        if (!class_exists(Xlsx::class)) {
            throw ValidationException::withMessages(['journalFile' => 'Reader XLSX belum tersedia pada instalasi ini.']);
        }
        $hash = hash_file('sha256', $file->getRealPath());
        $existing = JournalBatch::query()->where('organization_id', $orgId)
            ->where('source', $source)->where('fingerprint', $hash)->first();
        if ($existing) return $existing;
        $reader = new Xlsx();
        $reader->setReadDataOnly(true);
        $workbook = $reader->load($file->getRealPath());
        try {
            $sheet = $workbook->getActiveSheet();
            if (\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn()) > 100) {
                throw ValidationException::withMessages(['journalFile' => 'Maksimum 100 kolom per impor.']);
            }
            if ($sheet->getHighestRow() > 10001) {
                throw ValidationException::withMessages(['journalFile' => 'Maksimum 10.000 baris per impor.']);
            }
            $data = $sheet->toArray(null, false, false, false);
        } finally {
            $workbook->disconnectWorksheets();
        }
        if (count($data) < 2) throw ValidationException::withMessages(['journalFile' => 'File harus memiliki header dan data.']);
        $headers = array_map(fn ($h) => strtolower(trim((string)$h)), $data[0]);
        if (count($headers) !== count(array_unique($headers))) {
            throw ValidationException::withMessages(['journalFile' => 'Header duplikat.']);
        }
        $required = ['line_id', 'journal_no', 'date', 'account_code', 'debit', 'credit'];
        $columns = [];
        foreach (array_merge($required, ['unit_code','posting_status','entry_kind','related_reference']) as $field) {
            $header = strtolower(trim((string)($mapping[$field] ?? $field)));
            $index = array_search($header, $headers, true);
            if ($index === false && in_array($field, $required, true)) {
                throw ValidationException::withMessages(['journalFile' => 'Kolom wajib belum dipetakan: '.$field]);
            }
            $columns[$field] = $index === false ? null : $index;
        }
        $unitMap = Unit::query()->where('organization_id', $orgId)->pluck('id', 'code')->all();
        $lines = []; $seen = [];
        foreach (array_slice($data, 1) as $i => $row) {
            if (count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) continue;
            $get = fn ($field) => $columns[$field] === null ? null : ($row[$columns[$field]] ?? null);
            $lineId = trim((string)$get('line_id'));
            $journalNo = trim((string)$get('journal_no'));
            $account = trim((string)$get('account_code'));
            if ($lineId === '' || $journalNo === '' || $account === '' || isset($seen[$lineId])) {
                throw ValidationException::withMessages(['journalFile' => 'Identitas baris kosong/duplikat pada baris '.($i+2)]);
            }
            $seen[$lineId] = true;
            $date = $get('date');
            if (is_numeric($date)) {
                $date = ExcelDate::excelToDateTimeObject((float)$date)->format('Y-m-d');
            } else {
                $date = trim((string)$date);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))) {
                throw ValidationException::withMessages(['journalFile' => 'Tanggal harus yyyy-mm-dd pada baris '.($i+2)]);
            }
            $debit = $this->money($get('debit'), $i+2);
            $credit = $this->money($get('credit'), $i+2);
            if ($debit > 0 && $credit > 0) {
                throw ValidationException::withMessages(['journalFile' => 'Debit dan kredit tidak boleh sama-sama positif pada baris '.($i+2)]);
            }
            $unitCode = trim((string)($get('unit_code') ?? ''));
            if ($unitCode !== '' && !array_key_exists($unitCode, $unitMap)) {
                throw ValidationException::withMessages(['journalFile' => 'Kode unit tidak dikenal pada baris '.($i+2)]);
            }
            $kind = trim((string)($get('entry_kind') ?: 'unmapped'));
            if (!in_array($kind, ['source_expense','reclassification','internal_charge','clearing','reversal','unmapped'], true)) {
                throw ValidationException::withMessages(['journalFile' => 'Jenis jurnal belum dikenali pada baris '.($i+2)]);
            }
            $status = trim((string)($get('posting_status') ?: 'posted'));
            if (!in_array($status, ['draft','posted','reversed'], true)) {
                throw ValidationException::withMessages(['journalFile' => 'Status jurnal belum dikenali pada baris '.($i+2)]);
            }
            $lines[] = [
                'organization_id' => $orgId, 'source' => $source, 'external_line_id' => $lineId,
                'journal_no' => $journalNo, 'posted_on' => $date, 'account_code' => $account,
                'unit_id' => $unitCode === '' ? null : $unitMap[$unitCode],
                'debit' => $debit, 'credit' => $credit, 'entry_kind' => $kind,
                'posting_status' => $status, 'related_reference' => $get('related_reference'),
                'metadata' => ['sheet_row' => $i + 2],
            ];
        }
        if (!$lines) throw ValidationException::withMessages(['journalFile' => 'Tidak ada baris transaksi yang valid.']);
        return DB::transaction(function () use ($orgId, $userId, $source, $hash, $file, $lines) {
            $batch = JournalBatch::create([
                'organization_id' => $orgId, 'source' => $source, 'fingerprint' => $hash,
                'imported_by' => $userId, 'status' => 'staged', 'row_count' => count($lines),
                'metadata' => ['filename' => basename($file->getClientOriginalName()), 'format' => 'xlsx'],
            ]);
            foreach ($lines as $line) {
                $line['batch_id'] = $batch->id;
                JournalLine::create($line);
            }
            $this->audit->record($orgId, $userId, 'journal.imported', 'journal_batch', $batch->id,
                ['row_count' => count($lines), 'sha256' => $hash]);
            return $batch;
        });
    }

    private function money(mixed $value, int $row): string
    {
        $raw = trim((string)($value ?? '0'));
        if (!preg_match('/^\d{1,15}(?:\.\d{1,2})?$/', $raw)) {
            throw ValidationException::withMessages(['journalFile' => 'Debit/kredit bukan desimal valid pada baris '.$row]);
        }
        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        return $whole.'.'.str_pad($fraction, 2, '0');
    }
}
