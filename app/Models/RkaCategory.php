<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RkaCategory extends Model
{
    protected $fillable = ['name', 'code', 'direction', 'calculation_mode', 'document_prefix', 'is_active', 'sort_order'];
    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function entries() { return $this->hasMany(RkaEntry::class, 'category_id'); }

    public function save(array $options = [])
    {
        return DB::transaction(function () use ($options) {
            if ($this->exists) DB::table('rka_categories')->where('id', $this->id)->lockForUpdate()->first();
            return parent::save($options);
        });
    }

    public function delete()
    {
        return DB::transaction(function () {
            DB::table('rka_categories')->where('id', $this->id)->lockForUpdate()->first();
            return parent::delete();
        });
    }

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            if (!in_array($category->direction, ['income', 'expense'], true)
                || !in_array($category->calculation_mode, ['quantity_price', 'manual'], true)) {
                throw ValidationException::withMessages(['calculation_mode' => 'Jenis atau perhitungan kategori tidak valid.']);
            }
            if ($category->exists && $category->isDirty(['code', 'direction', 'calculation_mode', 'document_prefix'])
                && ($category->legacy_source !== null || $category->entries()->lockForUpdate()->first())) {
                throw ValidationException::withMessages(['code' => 'Kode, jenis, dan perhitungan kategori yang sudah digunakan tidak dapat diubah.']);
            }
            if ($category->exists && $category->isDirty('legacy_source')) {
                throw ValidationException::withMessages(['code' => 'Referensi kategori historis tidak dapat diubah.']);
            }
        });
        static::deleting(function (self $category): void {
            if ($category->legacy_source !== null || $category->entries()->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['category_id' => 'Kategori yang digunakan dapat dinonaktifkan, bukan dihapus.']);
            }
        });
    }
}
