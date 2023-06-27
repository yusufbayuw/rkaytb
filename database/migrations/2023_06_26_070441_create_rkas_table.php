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
        Schema::create('rkas', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_akun')->nullable();
            $table->string('uraian');
            $table->boolean('is_parent');
            $table->string('parent_id')->nullable();
            $table->string('unit_nama');
            $table->decimal('satuan')->nullable();
            $table->string('volume')->nullable();
            $table->decimal('harga_satuan')->nullable();
            $table->decimal('total')->nullable();
            $table->decimal('januari')->nullable();
            $table->decimal('februari')->nullable();
            $table->decimal('maret')->nullable();
            $table->decimal('april')->nullable();
            $table->decimal('mei')->nullable();
            $table->decimal('juni')->nullable();
            $table->decimal('juli')->nullable();
            $table->decimal('agustus')->nullable();
            $table->decimal('september')->nullable();
            $table->decimal('oktober')->nullable();
            $table->decimal('november')->nullable();
            $table->decimal('desember')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rkas');
    }
};
