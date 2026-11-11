<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rka_memberships', function (Blueprint $t) {
            $t->foreignId('unit_id')->nullable()->constrained('rka_units')->nullOnDelete();
        });
        Schema::create('rka_journal_account_mappings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('cycle_id')->constrained('rka_cycles')->cascadeOnDelete();
            $t->string('source', 60);
            $t->string('account_code', 255);
            $t->string('classification', 40); // income, expense, internal_income, internal_expense, clearing, non_pnl
            $t->string('category', 80)->nullable(); // optional organization-specific income category for exclusions
            $t->foreignId('unit_id')->nullable()->constrained('rka_units')->nullOnDelete();
            $t->date('valid_from');
            $t->date('valid_to');
            $t->boolean('active')->default(false);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->index(['organization_id', 'cycle_id', 'source', 'account_code'], 'rka_mapping_lookup');
        });
        Schema::create('rka_reconciliation_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('cycle_id')->constrained('rka_cycles')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->string('status', 20)->default('draft');
            $t->string('source_hash', 64);
            $t->json('report');
            $t->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->unique(['cycle_id', 'version'], 'rka_recon_version_unique');
        });
        Schema::create('rka_sharing_disputes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('cycle_id')->constrained('rka_cycles')->cascadeOnDelete();
            $t->foreignId('pool_id')->nullable()->constrained('rka_shared_pools')->nullOnDelete();
            $t->foreignId('unit_id')->nullable()->constrained('rka_units')->nullOnDelete();
            $t->text('reason'); $t->string('status', 20)->default('open');
            $t->text('resolution')->nullable();
            $t->foreignId('raised_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['cycle_id','status']);
        });
        Schema::table('rka_approval_policies', function (Blueprint $t) {
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('activated_at')->nullable();
            $t->unsignedInteger('version')->default(1);
        });
        Schema::table('rka_approval_requests', function (Blueprint $t) {
            $t->text('reason')->nullable();
            $t->string('source_hash', 64)->nullable();
            $t->foreignId('reconciliation_id')->nullable()->constrained('rka_reconciliation_runs')->nullOnDelete();
        });
        Schema::create('rka_cycle_closings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('cycle_id')->constrained('rka_cycles')->cascadeOnDelete();
            $t->foreignId('reconciliation_id')->constrained('rka_reconciliation_runs')->restrictOnDelete();
            $t->foreignId('approval_request_id')->constrained('rka_approval_requests')->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->string('source_hash', 64);
            $t->string('report_hash', 64);
            $t->json('report');
            $t->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('closed_at');
            $t->timestamps();
            $t->unique(['cycle_id','version'], 'rka_close_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rka_cycle_closings');
        // One column alteration per Blueprint for SQLite migration rollback.
        // Combining several dropColumn calls fails in Laravel's SQLite grammar.
        Schema::table('rka_approval_requests', fn (Blueprint $t) =>
            $t->dropConstrainedForeignId('reconciliation_id'));
        Schema::table('rka_approval_requests', fn (Blueprint $t) =>
            $t->dropColumn('reason'));
        Schema::table('rka_approval_requests', fn (Blueprint $t) =>
            $t->dropColumn('source_hash'));
        Schema::table('rka_approval_policies', fn (Blueprint $t) =>
            $t->dropConstrainedForeignId('activated_by'));
        Schema::table('rka_approval_policies', fn (Blueprint $t) =>
            $t->dropConstrainedForeignId('created_by'));
        Schema::table('rka_approval_policies', fn (Blueprint $t) =>
            $t->dropColumn('activated_at'));
        Schema::table('rka_approval_policies', fn (Blueprint $t) =>
            $t->dropColumn('version'));
        Schema::dropIfExists('rka_sharing_disputes');
        Schema::dropIfExists('rka_reconciliation_runs');
        Schema::dropIfExists('rka_journal_account_mappings');
        Schema::table('rka_memberships', function (Blueprint $t) {
            $t->dropConstrainedForeignId('unit_id');
        });
    }
};
