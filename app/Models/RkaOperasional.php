<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

class RkaOperasional extends Model
{
    use HasFactory, HasRecursiveRelationships;

    protected $guarded = [];

    public function children() {
        return $this->hasMany(RkaOperasional::class, 'parent_id');
    }

    public function childrenScoreSum() {
        return $this->children()->sum('total');
    }

    public function childrenScoreSumJanuari() {
        return $this->children()->sum('januari');
    }
    
    public function childrenScoreSumFebruari() {
        return $this->children()->sum('februari');
    }
    
    public function childrenScoreSumMaret() {
        return $this->children()->sum('maret');
    }
    
    public function childrenScoreSumApril() {
        return $this->children()->sum('april');
    }
    
    public function childrenScoreSumMei() {
        return $this->children()->sum('mei');
    }
    
    public function childrenScoreSumJuni() {
        return $this->children()->sum('juni');
    }
    
    public function childrenScoreSumJuli() {
        return $this->children()->sum('juli');
    }
    
    public function childrenScoreSumAgustus() {
        return $this->children()->sum('agustus');
    }
    
    public function childrenScoreSumSeptember() {
        return $this->children()->sum('september');
    }
    
    public function childrenScoreSumOktober() {
        return $this->children()->sum('oktober');
    }
    
    public function childrenScoreSumNovember() {
        return $this->children()->sum('november');
    }
    
    public function childrenScoreSumDesember() {
        return $this->children()->sum('desember');
    }
}
