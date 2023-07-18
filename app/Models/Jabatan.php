<?php

namespace App\Models;

use App\Models\Kpi;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Jabatan extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function pegawai()
    {
        return $this->belongsToMany(Pegawai::class, 'pegawai_jabatans', 'id_jabatan', 'id_pegawai');
    }

    public function kpi()
    {
        return $this->belongsToMany(Kpi::class, 'jabatan_kpis', 'id_jabatan', 'id_kpi');
    }
}
