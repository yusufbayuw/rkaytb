<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rka_shared_pool_impact_assessments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('cycle_id')->constrained('rka_cycles')->cascadeOnDelete();
            $t->foreignId('pool_id')->constrained('rka_shared_pools')->cascadeOnDelete();
            $t->unsignedInteger('pool_version');
            $t->string('allocation_hash', 64);
            $t->string('change_type', 32); // baseline, routine, parameters, transfer
            $t->string('review_tier', 16); // inform, review, approve
            $t->text('reason');
            $t->timestamp('response_due_at')->nullable();
            $t->string('status', 20)->default('draft');
            $t->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->unique(['pool_id', 'pool_version'], 'rka_impact_pool_version');
        });
        Schema::create('rka_shared_pool_escalations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('cycle_id')->constrained('rka_cycles')->cascadeOnDelete();
            $t->foreignId('pool_id')->constrained('rka_shared_pools')->cascadeOnDelete();
            $t->foreignId('unit_id')->constrained('rka_units')->restrictOnDelete();
            $t->unsignedInteger('pool_version');
            $t->string('allocation_hash', 64);
            $t->text('reason');
            $t->string('status', 20)->default('pending');
            $t->foreignId('proposed_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->unique(['pool_id', 'unit_id', 'pool_version'], 'rka_impact_escalation_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rka_shared_pool_escalations');
        Schema::dropIfExists('rka_shared_pool_impact_assessments');
    }
};
