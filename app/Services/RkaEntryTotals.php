<?php

namespace App\Services;

use App\Models\{RkaCategory, RkaEntry, Unit};
use App\Support\RkaDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RkaEntryTotals
{
    public function beforeSave(RkaEntry $entry): void
    {
        if ($entry->exists && $entry->isDirty(['origin_source', 'origin_id', 'origin_snapshot', 'migration_hash', 'migration_issues', 'document_number'])) {
            throw ValidationException::withMessages(['category_id' => 'Identitas dan referensi historis RKA tidak dapat diubah.']);
        }
        if (!$entry->exists && ($entry->origin_source !== null || $entry->origin_id !== null
            || $entry->origin_snapshot !== null || $entry->migration_hash !== null || $entry->migration_issues !== null)) {
            throw ValidationException::withMessages(['category_id' => 'Referensi historis hanya dapat dibentuk melalui migrasi data.']);
        }
        $category = RkaCategory::whereKey($entry->category_id)->lockForUpdate()->first();
        if (!$category || ((!$entry->exists || $entry->isDirty('category_id')) && !$category->is_active)) {
            throw ValidationException::withMessages(['category_id' => 'Pilih kategori RKA yang aktif.']);
        }
        if ($entry->exists && $entry->isDirty('category_id')) {
            throw ValidationException::withMessages(['category_id' => 'Kategori anggaran yang sudah tersimpan tidak dapat diubah.']);
        }
        if (!$entry->exists) {
            $entry->document_number = $category->next_document_number;
            DB::table('rka_categories')->where('id', $category->id)->update(['next_document_number' => $category->next_document_number + 1]);
        }
        if (!$entry->exists || $entry->isDirty('unit_nama')) {
            if (!Unit::whereKey($entry->unit_nama)->lockForUpdate()->first()) throw ValidationException::withMessages(['unit_nama' => 'Unit tidak tersedia.']);
            if ($entry->exists && $entry->children()->lockForUpdate()->first()) throw ValidationException::withMessages(['unit_nama' => 'Unit induk yang mempunyai anak tidak dapat dipindahkan.']);
        }
        if ($entry->exists && $entry->isDirty('is_parent') && !$entry->is_parent && $entry->children()->lockForUpdate()->first()) {
            throw ValidationException::withMessages(['is_parent' => 'Induk masih mempunyai rincian anak.']);
        }
        $visited = [$entry->id => true];
        $id = $entry->parent_id;
        while ($id !== null) {
            if (isset($visited[$id]) || count($visited) > 100) throw ValidationException::withMessages(['parent_id' => 'Hierarki RKA tidak boleh membentuk siklus atau terlalu dalam.']);
            $visited[$id] = true;
            $parent = RkaEntry::whereKey($id)->lockForUpdate()->first();
            if (!$parent || !$parent->is_parent || (int) $parent->category_id !== (int) $entry->category_id
                || (string) $parent->unit_nama !== (string) $entry->unit_nama) {
                throw ValidationException::withMessages(['parent_id' => 'Induk harus berada pada kategori dan unit yang sama.']);
            }
            $id = $parent->parent_id;
        }
        foreach (RkaEntry::MONTHS as $month) {
            if ($entry->isDirty($month)) $entry->$month = RkaDecimal::input($entry->$month ?? '0', $month);
        }
        if ($entry->is_parent) return;
        if ($category->calculation_mode === 'manual') {
            if (!$entry->exists || $entry->isDirty('total')) $entry->total = RkaDecimal::input($entry->total, 'total');
            if ($entry->isDirty(['volume', 'harga_satuan']) && ($entry->volume !== null || $entry->harga_satuan !== null)) {
                throw ValidationException::withMessages(['total' => 'Kategori ini memakai total manual.']);
            }
        } elseif (!$entry->exists || $entry->isDirty(['volume', 'harga_satuan', 'total', 'is_parent'])) {
            $volume = RkaDecimal::input($entry->volume, 'volume', 4);
            $price = RkaDecimal::input($entry->harga_satuan, 'harga_satuan', 4);
            $entry->volume = $volume;
            $entry->harga_satuan = $price;
            $entry->total = RkaDecimal::round(bcmul($volume, $price, 8));
        }
        if ($entry->isDirty('total')) {
            $entry->total_view = $entry->total;
            $entry->total_terbilang = app(LegacyRkaTotals::class)->spellAmount((string) $entry->total);
        }
    }

    public function afterSave(RkaEntry $entry): void
    {
        if (!$entry->wasRecentlyCreated && !$entry->isDirty(['total', 'parent_id', 'is_parent', ...RkaEntry::MONTHS])) return;
        if ($entry->is_parent) $this->updateChain($entry->id);
        $this->updateChain($entry->parent_id);
        if ($entry->isDirty('parent_id')) $this->updateChain($entry->getRawOriginal('parent_id'));
    }

    public function updateChain(?int $id): void
    {
        $visited = [];
        while ($id !== null) {
            if (isset($visited[$id]) || count($visited) > 100) throw ValidationException::withMessages(['parent_id' => 'Hierarki RKA tidak valid.']);
            $visited[$id] = true;
            $parent = DB::table('rka_entries')->where('id', $id)->lockForUpdate()->first();
            if (!$parent) return;
            if ($parent->is_parent) {
                $values = array_fill_keys(['total', ...RkaEntry::MONTHS], '0.00');
                foreach (DB::table('rka_entries')->where('parent_id', $id)->lockForUpdate()->get(array_keys($values)) as $child) {
                    foreach ($values as $key => $amount) $values[$key] = RkaDecimal::round(bcadd($amount, RkaDecimal::stored($child->$key), 2));
                }
                $values['total_view'] = $values['total'];
                $values['total_terbilang'] = app(LegacyRkaTotals::class)->spellAmount($values['total']);
                $changed = false;
                foreach ($values as $key => $value) if ((string) $parent->$key !== $value) { $changed = true; break; }
                if ($changed) DB::table('rka_entries')->where('id', $id)->update([...$values, 'updated_at' => now()]);
            }
            $id = $parent->parent_id;
        }
    }
}
