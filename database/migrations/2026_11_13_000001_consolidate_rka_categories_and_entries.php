<?php

use App\Services\RkaConsolidation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DDL can commit independently on MySQL; a retry resumes the data copy.
        if (!Schema::hasTable('rka_categories')) Schema::create('rka_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('direction', 20);
            $table->string('calculation_mode', 30);
            $table->string('document_prefix', 20)->unique();
            $table->unsignedBigInteger('next_document_number')->default(1);
            $table->string('legacy_source')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        if (!Schema::hasTable('rka_entries')) Schema::create('rka_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('rka_categories')->restrictOnDelete();
            $table->unsignedBigInteger('document_number');
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('unit_nama')->index();
            $table->string('nomor_akun')->nullable();
            $table->string('uraian');
            $table->string('uraian_view')->nullable();
            $table->boolean('is_parent')->default(false);
            $table->boolean('is_detail')->default(false);
            $table->string('volume')->nullable();
            $table->string('harga_satuan')->nullable();
            $table->string('satuan')->nullable();
            $table->string('total')->nullable();
            $table->string('total_view')->nullable();
            $table->string('total_terbilang')->nullable();
            foreach (['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'] as $month) $table->string($month)->nullable();
            $table->string('origin_source')->nullable();
            $table->unsignedBigInteger('origin_id')->nullable();
            $table->json('origin_snapshot')->nullable();
            $table->json('migration_issues')->nullable();
            $table->string('migration_hash', 64)->nullable();
            $table->unique(['origin_source', 'origin_id']);
            $table->unique(['category_id', 'document_number']);
            $table->index(['category_id', 'unit_nama']);
            $table->timestamps();
        });
        (new RkaConsolidation)->import();
    }

    public function down(): void
    {
        (new RkaConsolidation)->assertRollbackSafe();
        Schema::dropIfExists('rka_entries');
        Schema::dropIfExists('rka_categories');
    }
};
