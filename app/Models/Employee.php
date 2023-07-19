<?php

namespace App\Models;

use App\Models\Unit;
use App\Models\Position;
use App\Models\EmployeeKpiScore;
use App\Events\EmployeePositionCreated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Employee extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function kpiScores()
    {
        return $this->hasMany(EmployeeKpiScore::class);
    }

    protected static function boot()
{
    parent::boot();

    static::created(function ($employee) {
        if (isset($employee->position_id))
        {
            event(new EmployeePositionCreated($employee));
        }
    });
}

}
