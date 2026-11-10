<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rka_book_sections', function (Blueprint $t) {
            $t->string('block_type', 40)->default('narrative');
        });
    }

    public function down(): void
    {
        Schema::table('rka_book_sections', function (Blueprint $t) {
            $t->dropColumn('block_type');
        });
    }
};
