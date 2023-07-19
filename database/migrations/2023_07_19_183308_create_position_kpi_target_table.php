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
        Schema::create('position_kpi_target', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('position_id');
            $table->unsignedBigInteger('kpi_target_id');

            $table->foreign('position_id')->references('id')->on('positions')->onDelete('cascade');
            $table->foreign('kpi_target_id')->references('id')->on('kpi_targets')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('position_kpi_target');
    }
};
