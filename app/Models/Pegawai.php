<?php

namespace App\Models;

use App\Models\Jabatan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Pegawai extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function jabatan()
    {
        return $this->belongsToMany(Jabatan::class, 'pegawai_jabatans', 'id_pegawai', 'id_jabatan');
    }
}
