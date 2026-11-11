<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rka_materiality_policies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('cycle_id')->constrained('rka_cycles')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->unsignedBigInteger('absolute_minor');
            $t->unsignedSmallInteger('percentage_bps'); // 100 bps = 1%
            $t->string('status', 20)->default('draft');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->unique(['cycle_id','version'], 'rka_materiality_ver_unique');
        });
        Schema::create('rka_close_exceptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('cycle_id')->constrained('rka_cycles')->cascadeOnDelete();
            $t->foreignId('budget_line_id')->constrained('rka_budget_lines')->restrictOnDelete();
            $t->foreignId('policy_id')->nullable()->constrained('rka_materiality_policies')->nullOnDelete();
            $t->string('code', 60);
            $t->unsignedBigInteger('amount_minor');
            $t->text('reason');
            $t->string('status', 20)->default('requested');
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->index(['organization_id','cycle_id','status'], 'rka_exception_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rka_close_exceptions');
        Schema::dropIfExists('rka_materiality_policies');
    }
};
