<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rka_book_sections', function (Blueprint $table) {
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }
    public function down(): void
    {
        // These columns belong to tables introduced by 000001. SQLite cannot
        // independently drop their foreign keys; full nine-step rollback
        // removes the entire platform table while preserving legacy tables.
        if (Schema::getConnection()->getDriverName() === 'sqlite') return;
        Schema::table('rka_book_sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_edited_by');
        });
    }
};
