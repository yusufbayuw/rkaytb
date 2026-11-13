<?php

namespace App\Models;

use App\Services\RkaEntryTotals;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

class RkaEntry extends Model
{
    use HasRecursiveRelationships;

    public const MONTHS = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];
    protected $fillable = ['category_id', 'nomor_akun', 'uraian', 'uraian_view', 'unit_nama', 'parent_id',
        'is_parent', 'is_detail', 'volume', 'harga_satuan', 'satuan', 'total', ...self::MONTHS];
    protected $casts = ['is_parent' => 'boolean', 'is_detail' => 'boolean', 'origin_snapshot' => 'array', 'migration_issues' => 'array'];
    protected $attributes = ['is_parent' => false, 'is_detail' => false];

    public function category() { return $this->belongsTo(RkaCategory::class, 'category_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }
    public function save(array $options = [])
    {
        return DB::transaction(function () use ($options) {
            DB::table('rka_categories')->where('id', $this->category_id)->lockForUpdate()->first();
            if ($this->exists) $this->assertFreshForWrite();
            return parent::save($options);
        });
    }
    public function delete()
    {
        return DB::transaction(function () {
            DB::table('rka_categories')->where('id', $this->category_id)->lockForUpdate()->first();
            $this->assertFreshForWrite();
            return parent::delete();
        });
    }

    protected static function booted(): void
    {
        static::saving(fn (self $entry) => app(RkaEntryTotals::class)->beforeSave($entry));
        static::saved(fn (self $entry) => app(RkaEntryTotals::class)->afterSave($entry));
        static::deleting(function (self $entry): void {
            if ($entry->children()->lockForUpdate()->first()) throw ValidationException::withMessages(['parent_id' => 'Hapus atau pindahkan rincian anak sebelum menghapus induk RKA.']);
        });
        static::deleted(fn (self $entry) => app(RkaEntryTotals::class)->updateChain($entry->parent_id));
    }

    private function assertFreshForWrite(): void
    {
        $current = DB::table('rka_entries')->where('id', $this->id)->lockForUpdate()->first();
        $fields = ['category_id', 'unit_nama', 'parent_id', 'is_parent', 'is_detail', 'volume', 'harga_satuan',
            'satuan', 'nomor_akun', 'uraian', 'document_number', 'origin_source', 'origin_id'];
        if (!(bool) $this->getRawOriginal('is_parent')) $fields = [...$fields, 'total', ...self::MONTHS];
        foreach ($fields as $field) {
            $before = $this->getRawOriginal($field); $now = $current->$field ?? null;
            if (in_array($field, ['is_parent', 'is_detail'], true)) { $before = (int) $before; $now = (int) $now; }
            if (!$current || ($before === null ? $now !== null : (string) $before !== (string) $now)) {
                throw ValidationException::withMessages(['uraian' => 'Data RKA sudah berubah. Muat ulang sebelum menyimpan atau menghapus.']);
            }
        }
    }

    public function manual(): bool { return $this->category->calculation_mode === 'manual'; }
    public function monthlyBalance(): ?string
    {
        try {
            $sum = '0.00';
            foreach (self::MONTHS as $month) $sum = bcadd($sum, \App\Support\RkaDecimal::stored($this->$month), 2);
            return bcsub(\App\Support\RkaDecimal::stored($this->total), $sum, 2);
        } catch (ValidationException) { return null; }
    }
    public function documentCode(): string
    {
        if ($this->is_parent) return '';
        return $this->category->document_prefix.'-'.str_pad((string) $this->document_number, 6, '0', STR_PAD_LEFT);
    }
}
