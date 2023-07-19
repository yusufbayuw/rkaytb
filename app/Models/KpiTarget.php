<?php

namespace App\Models;

use App\Models\Position;
use App\Models\EmployeeKpiScore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KpiTarget extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function positions()
    {
        return $this->belongsToMany(Position::class);
    }

    public function kpiScores()
    {
        return $this->hasMany(EmployeeKpiScore::class);
    }
}
