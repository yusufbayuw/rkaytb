<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RkaConsolidation
{
    public const CATEGORIES = [
        'investment' => ['rkas', 'Investasi', 'expense', 'quantity_price', 'Inv'],
        'maintenance' => ['rka_maintenances', 'Maintenance', 'expense', 'quantity_price', 'Mai'],
        'consumables' => ['rka_consumables', 'Consumable', 'expense', 'quantity_price', 'Con'],
        'operational' => ['rka_operasionals', 'Operasional', 'expense', 'quantity_price', 'Ope'],
        'salary' => ['rka_gajis', 'Gaji', 'expense', 'manual', 'Gaj'],
        'revenue' => ['rka_pendapatans', 'Pendapatan', 'income', 'quantity_price', 'Pen'],
    ];
    public const FIELDS = ['nomor_akun', 'uraian', 'uraian_view', 'is_parent', 'is_detail', 'total_view', 'total_terbilang',
        'unit_nama', 'satuan', 'volume', 'harga_satuan', 'total', 'januari', 'februari', 'maret', 'april', 'mei',
        'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember', 'created_at', 'updated_at'];

    public function import(): void
    {
        DB::transaction(function (): void {
            $order = 0;
            foreach (self::CATEGORIES as $code => [$table, $name, $direction, $mode, $prefix]) {
                DB::table('rka_categories')->insertOrIgnore(['code' => $code, 'name' => $name, 'direction' => $direction,
                    'calculation_mode' => $mode, 'document_prefix' => $prefix, 'legacy_source' => $table,
                    'is_active' => true, 'sort_order' => ++$order, 'created_at' => now(), 'updated_at' => now()]);
                $category = DB::table('rka_categories')->where('code', $code)->first();
                $source = DB::table($table)->orderBy('id')->get()->keyBy('id');
                foreach ($source as $row) {
                    if (DB::table('rka_entries')->where('origin_source', $table)->where('origin_id', $row->id)->exists()) continue;
                    $data = [];
                    foreach (self::FIELDS as $field) $data[$field] = $row->$field ?? null;
                    $data['is_parent'] = (bool) ($row->is_parent ?? false);
                    $data['is_detail'] = (bool) ($row->is_detail ?? false);
                    DB::table('rka_entries')->insert([...$data, 'category_id' => $category->id, 'origin_source' => $table,
                        'origin_id' => $row->id, 'document_number' => $row->id,
                        'origin_snapshot' => json_encode((array) $row, JSON_THROW_ON_ERROR)]);
                }
                $mapping = DB::table('rka_entries')->where('origin_source', $table)->pluck('id', 'origin_id');
                foreach ($source as $row) {
                    $parentId = $row->parent_id ?? null;
                    $issues = [];
                    $seen = [$row->id => true];
                    $cursor = $parentId;
                    while ($cursor !== null) {
                        if (isset($seen[$cursor]) || count($seen) > 100) { $issues[] = 'cyclic_or_deep_hierarchy'; break; }
                        $seen[$cursor] = true;
                        $parent = $source[$cursor] ?? null;
                        if (!$parent) { $issues[] = 'missing_parent'; break; }
                        if (!($parent->is_parent ?? false)) { $issues[] = 'parent_is_not_container'; break; }
                        if ((string) $parent->unit_nama !== (string) $row->unit_nama) { $issues[] = 'parent_from_other_unit'; break; }
                        $cursor = $parent->parent_id ?? null;
                    }
                    $entry = DB::table('rka_entries')->where('id', $mapping[$row->id])->first();
                    $snapshot = json_decode($entry->origin_snapshot, true, flags: JSON_THROW_ON_ERROR);
                    $currentSource = (array) $row;
                    ksort($snapshot); ksort($currentSource);
                    if ($snapshot != $currentSource) throw new \RuntimeException('Arsip RKA berubah sejak migrasi. Impor ulang ditolak agar referensi historis tidak tertimpa.');
                    if ($entry->migration_hash !== null && !hash_equals($entry->migration_hash, self::fingerprint($entry))) {
                        throw new \RuntimeException('RKA aktif sudah berubah; impor ulang tidak boleh menimpa perubahan pengguna.');
                    }
                    $values = ['parent_id' => !$issues && $parentId !== null ? $mapping[$parentId] : null,
                        'migration_issues' => json_encode($issues, JSON_THROW_ON_ERROR)];
                    DB::table('rka_entries')->where('id', $entry->id)->update($values);
                    $fresh = DB::table('rka_entries')->where('id', $entry->id)->first();
                    DB::table('rka_entries')->where('id', $entry->id)->update(['migration_hash' => self::fingerprint($fresh)]);
                }
                $next = ((int) $source->keys()->max()) + 1;
                DB::table('rka_categories')->where('id', $category->id)->where('next_document_number', '<', $next)
                    ->update(['next_document_number' => $next]);
            }
        });
    }

    public static function fingerprint(object $entry): string
    {
        $data = [];
        foreach ([...self::FIELDS, 'category_id', 'document_number', 'parent_id', 'origin_source', 'origin_id', 'origin_snapshot', 'migration_issues'] as $field) {
            $value = $entry->$field ?? null;
            if (in_array($field, ['is_parent', 'is_detail'], true)) $value = (int) $value;
            // IDs/booleans have different PDO scalar types on SQLite and MySQL.
            $data[$field] = $value === null ? null : (string) $value;
        }
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function assertRollbackSafe(): void
    {
        if (DB::table('rka_categories')->count() !== count(self::CATEGORIES)
            || DB::table('rka_entries')->where(fn ($q) => $q->whereNull('origin_source')
                ->orWhereNotIn('origin_source', array_column(array_values(self::CATEGORIES), 0)))->exists()) $this->unsafeRollback();
        $order = 0;
        foreach (self::CATEGORIES as $code => [$table, $name, $direction, $mode, $prefix]) {
            $category = DB::table('rka_categories')->where('code', $code)->first();
            if (!$category || $category->name !== $name || $category->direction !== $direction
                || $category->calculation_mode !== $mode || $category->document_prefix !== $prefix || !$category->is_active
                || $category->legacy_source !== $table || (int) $category->sort_order !== ++$order) $this->unsafeRollback();
            if ((int) $category->next_document_number !== ((int) DB::table($table)->max('id')) + 1) $this->unsafeRollback();
            if (DB::table('rka_entries')->where('origin_source', $table)->count() !== DB::table($table)->count()) $this->unsafeRollback();
            foreach (DB::table('rka_entries')->where('origin_source', $table)->cursor() as $entry) {
                if (!$entry->migration_hash || !hash_equals($entry->migration_hash, self::fingerprint($entry))) $this->unsafeRollback();
            }
        }
    }

    private function unsafeRollback(): never
    {
        throw new \RuntimeException('Rollback konsolidasi ditolak: terdapat perubahan RKA aktif. Pertahankan data dan gunakan migrasi maju atau cadangan yang sudah diverifikasi.');
    }
}
