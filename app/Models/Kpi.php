<?php

namespace App\Models;

use App\Models\Jabatan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Kpi extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function jabatan()
    {
        return $this->belongsToMany(Jabatan::class, 'jabatan_kpis', 'id_kpi', 'id_jabatan');
    }
}
