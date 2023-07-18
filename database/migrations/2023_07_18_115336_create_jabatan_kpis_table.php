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
        Schema::create('jabatan_kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_jabatan')->constrained('jabatans');
            $table->foreignId('id_kpi')->constrained('kpis');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jabatan_kpis');
    }
};
