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
        return $this->belongsToMany(Position::class, 'position_kpi_target', 'kpi_target_id', 'position_id');
    }

    public function kpiScores()
    {
        return $this->hasMany(EmployeeKpiScore::class);
    }
}
