<?php

namespace App\Models\Concerns;

use App\Services\LegacyRkaTotals;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait HasLegacyRkaTotals
{
    /**
     * Leaf changes and parent aggregates must be committed or rolled back together.
     * Query-builder bulk updates are intentionally not used by this workflow.
     */
    public function save(array $options = [])
    {
        return DB::transaction(fn () => parent::save($options));
    }

    public function delete()
    {
        return DB::transaction(fn () => parent::delete());
    }

    protected static function bootHasLegacyRkaTotals(): void
    {
        static::saving(fn ($record) => app(LegacyRkaTotals::class)->beforeSave($record));
        static::saved(fn ($record) => app(LegacyRkaTotals::class)->afterSave($record));
        static::deleting(function ($record): void {
            if ($record->children()->exists()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Hapus atau pindahkan rincian anak sebelum menghapus induk RKA.',
                ]);
            }
        });
        static::deleted(fn ($record) => app(LegacyRkaTotals::class)->afterDelete($record));
    }
}
