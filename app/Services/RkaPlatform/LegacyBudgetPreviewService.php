<?php

namespace App\Services\RkaPlatform;

use App\Models\User;
use App\Models\Unit as LegacyUnit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LegacyBudgetPreviewService
{
    // Names of existing Laravel tables; no organization identifiers or business assumptions.
    public const SOURCES = [
        'investment' => 'rkas',
        'operational' => 'rka_operasionals',
        'salary' => 'rka_gajis',
        'revenue' => 'rka_pendapatans',
        'consumables' => 'rka_consumables',
        'maintenance' => 'rka_maintenances',
    ];

    // Legacy records lack organization IDs and a guaranteed budget-year mapping.
    // Global superadmin may inspect only; no import/posting happens here.
    public function preview(int $actorId, string $source, int $legacyUnitId): array
    {
        $actor = User::findOrFail($actorId);
        if (!$actor->hasRole('superadmin')) {
            throw new AuthorizationException('Data RKA lama hanya dapat diaudit oleh superadmin.');
        }
        if (!isset(self::SOURCES[$source])) {
            throw ValidationException::withMessages(['legacySource' => 'Sumber RKA lama tidak dikenali.']);
        }
        $unit = LegacyUnit::findOrFail($legacyUnitId);
        $table = self::SOURCES[$source];
        if (!Schema::hasTable($table)) {
            throw ValidationException::withMessages(['legacySource' => 'Tabel RKA lama tidak tersedia.']);
        }
        $query = DB::table($table)->where('unit_nama', (string)$legacyUnitId);
        $rows = (clone $query)->orderBy('id')->limit(50)->get(['id','nomor_akun','uraian','total'])
            ->map(fn ($row) => [
                'id' => $row->id,
                'account' => (string)($row->nomor_akun ?? ''),
                'description' => (string)($row->uraian ?? ''),
                'total_as_stored' => (string)($row->total ?? ''),
            ])->all();
        return [
            'unit' => $unit->nama,
            'source' => $source,
            'count' => (clone $query)->count(),
            'sample' => $rows,
            'warning' => 'Data referensi read-only; periode, angka induk-anak, serta mapping organisasi belum diverifikasi. Tidak masuk buku atau KPI final.',
        ];
    }
}
