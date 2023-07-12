<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

class Rka extends Model
{
    use HasFactory, HasRecursiveRelationships;

    protected $guarded = [];

    public function children() {
        return $this->hasMany(Rka::class, 'parent_id');
    }

    public function childrenScoreSum($column) {
        return $this->children()->sum($column);
    }
    
}
