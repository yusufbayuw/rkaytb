<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pegawai_jabatan_kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_pegawai_jabatan')->constrained('pegawai_jabatans');
            $table->foreignId('id_jabatan_kpi')->constrained('jabatan_kpis');
            $table->integer('capaian');
            $table->integer('persentase_capaian');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pegawai_jabatan_kpis');
    }
};
