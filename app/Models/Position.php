<?php

namespace App\Models;

use App\Models\Employee;
use App\Models\KpiTarget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

class Position extends Model
{
    use HasFactory, HasRecursiveRelationships;

    protected $guarded = [];

    public function children() {
        return $this->hasMany(Position::class, 'parent_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function kpiTargets()
    {
        return $this->belongsToMany(KpiTarget::class, 'position_kpi_target', 'position_id', 'kpi_target_id');
    }
}
