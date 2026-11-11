<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

class Rka extends Model
{
    use HasFactory, HasRecursiveRelationships;

    protected $guarded = [];

    /**
     * A leaf write and its stored ancestor aggregates form one financial
     * transaction. Model hooks alone would otherwise commit the leaf first.
     */
    public function save(array $options = [])
    {
        return \Illuminate\Support\Facades\DB::transaction(fn () => parent::save($options));
    }

    public function delete()
    {
        return \Illuminate\Support\Facades\DB::transaction(fn () => parent::delete());
    }

    protected static function booted(): void
    {
        static::saving(fn (self $record) => app(\App\Services\LegacyRkaTotals::class)->beforeSave($record));
        static::saved(fn (self $record) => app(\App\Services\LegacyRkaTotals::class)->afterSave($record));
        static::deleted(fn (self $record) => app(\App\Services\LegacyRkaTotals::class)->afterDelete($record));
    }

    public function children() {
        return $this->hasMany(Rka::class, 'parent_id');
    }

    public function childrenScoreSum($column) {
        return $this->children()->sum($column);
    }
    
}
