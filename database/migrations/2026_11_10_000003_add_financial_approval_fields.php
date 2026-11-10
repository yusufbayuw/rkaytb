<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['rka_budget_lines', 'rka_shared_pools'] as $name) {
            Schema::table($name, function (Blueprint $t) {
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('approved_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['rka_shared_pools', 'rka_budget_lines'] as $name) {
            Schema::table($name, function (Blueprint $t) {
                $t->dropConstrainedForeignId('created_by');
                $t->dropConstrainedForeignId('approved_by');
                $t->dropColumn('approved_at');
            });
        }
    }
};
