<?php

namespace App\Models;

use App\Models\Employee;
use App\Models\KpiTarget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmployeeKpiScore extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function kpiTarget()
    {
        return $this->belongsTo(KpiTarget::class);
    }


}
