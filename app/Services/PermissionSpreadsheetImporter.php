<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSpreadsheetImporter
{
    private function rows(UploadedFile $file): array
    {
        Gate::authorize('create', Permission::class);
        \App\Support\SystemAccess::authorize();
        if ($file->getSize() > 10 * 1024 * 1024 || !in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'xlsx', 'xls', 'txt'], true)) {
            throw ValidationException::withMessages(['file' => 'Gunakan CSV, XLS atau XLSX maksimal 10 MB.']);
        }
        try {
            $extension = strtolower($file->getClientOriginalExtension());
            $expected = match ($extension) { 'xlsx' => 'Xlsx', 'xls' => 'Xls', default => 'Csv' };
            $reader = IOFactory::createReader(IOFactory::identify($file->getRealPath(), [$expected]));
            $info = $reader->listWorksheetInfo($file->getRealPath())[0];
            if ($info['totalRows'] > 10001 || $info['totalColumns'] > 100 || $info['totalRows'] * $info['totalColumns'] > 200000) {
                throw new \RuntimeException('Berkas melebihi batas impor.');
            }
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly($info['worksheetName']);
            $book = $reader->load($file->getRealPath());
            try {
                return $book->getSheet(0)->toArray(null, false, false, false);
            } finally {
                $book->disconnectWorksheets();
            }
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['file' => 'Berkas tidak dapat dibaca atau melebihi 10.000 baris / 200.000 sel.']);
        }
    }

    public function headers(UploadedFile $file, bool $hasHeader = true): array
    {
        $rows = $this->rows($file);
        $options = [];
        foreach ($rows[0] ?? [] as $i => $value) {
            $options[(string) $i] = $hasHeader ? (string) ($value ?? 'Kolom '.($i + 1)) : 'Kolom '.($i + 1);
        }
        return $options;
    }

    public function import(UploadedFile $file, int $column, bool $hasHeader = true): array
    {
        $rows = $this->rows($file);
        if ($column < 0 || !array_key_exists($column, $rows[0] ?? [])) {
            throw ValidationException::withMessages(['column' => 'Pilih kolom nama permission yang tersedia.']);
        }
        $result = ['created' => 0, 'duplicates' => 0, 'errors' => []];
        DB::transaction(function () use ($rows, $column, $hasHeader, &$result) {
            foreach ($rows as $i => $row) {
                if (($hasHeader && $i === 0) || !array_filter($row, fn ($v) => $v !== null && $v !== '')) continue;
                $name = trim((string) ($row[$column] ?? ''));
                $validator = Validator::make(['name' => $name], ['name' => ['required', 'string', 'max:255']]);
                if ($validator->fails()) {
                    $result['errors'][] = ['row' => $i + 1, 'message' => 'Nama permission wajib diisi, maksimal 255 karakter.'];
                    continue;
                }
                $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => config('filament.auth.guard', 'web')]);
                $result[$permission->wasRecentlyCreated ? 'created' : 'duplicates']++;
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return $result;
    }
}
