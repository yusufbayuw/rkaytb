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
        Schema::create('rka_pendapatans', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_akun')->nullable();
            $table->string('uraian');
            $table->string('uraian_view')->nullable();
            $table->boolean('is_parent');
            $table->boolean('is_detail')->default(false);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('total_view')->nullable();
            $table->string('total_terbilang')->nullable();
            $table->string('unit_nama');
            $table->string('jumlah')->nullable();
            $table->string('satuan')->nullable();
            $table->string('volume')->nullable();
            $table->string('harga_satuan')->nullable();
            $table->string('total')->nullable();
            $table->string('januari')->nullable();
            $table->string('februari')->nullable();
            $table->string('maret')->nullable();
            $table->string('april')->nullable();
            $table->string('mei')->nullable();
            $table->string('juni')->nullable();
            $table->string('juli')->nullable();
            $table->string('agustus')->nullable();
            $table->string('september')->nullable();
            $table->string('oktober')->nullable();
            $table->string('november')->nullable();
            $table->string('desember')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rka_pendapatans');
    }
};
