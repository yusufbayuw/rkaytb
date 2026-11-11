<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rka_organizations', function (Blueprint $t) {
            $t->id();
            $t->string('name'); $t->string('slug')->unique();
            $t->string('brand_name')->nullable(); $t->string('logo_path')->nullable();
            $t->string('primary_color', 7)->default('#1e3a5f');
            $t->string('currency', 8)->default('IDR'); $t->string('locale', 16)->default('id');
            $t->string('timezone', 64)->default('Asia/Jakarta');
            $t->unsignedTinyInteger('fiscal_year_start')->default(1);
            $t->json('settings')->nullable(); $t->timestamps();
        });
        Schema::create('rka_memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->json('roles'); $t->boolean('active')->default(true); $t->timestamps();
            $t->unique(['organization_id','user_id']);
        });
        Schema::create('rka_units', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('rka_units')->nullOnDelete();
            $t->string('code'); $t->string('name'); $t->boolean('active')->default(true);
            $t->json('attributes')->nullable(); $t->timestamps();
            $t->unique(['organization_id','code']);
        });
        Schema::create('rka_cycles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->string('name'); $t->unsignedInteger('year');
            $t->date('starts_on'); $t->date('ends_on');
            $t->string('status')->default('draft'); $t->json('settings')->nullable();
            $t->timestamps(); $t->unique(['organization_id','year']);
        });
        Schema::create('rka_budget_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('cycle_id')->constrained('rka_cycles')->cascadeOnDelete();
            $t->foreignId('unit_id')->nullable()->constrained('rka_units')->nullOnDelete();
            $t->string('account_code')->nullable(); $t->string('category');
            $t->string('name'); $t->string('program_code')->nullable();
            $t->decimal('amount', 20, 2)->default(0); $t->json('period_amounts')->nullable();
            $t->string('status')->default('draft'); $t->json('metadata')->nullable();
            $t->timestamps(); $t->index(['organization_id','cycle_id']);
        });
        Schema::create('rka_shared_pools', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('cycle_id')->constrained('rka_cycles')->cascadeOnDelete();
            $t->string('code'); $t->string('name'); $t->string('method')->default('equal');
            $t->decimal('amount', 20, 2)->default(0);
            $t->json('drivers')->nullable(); $t->json('allocations')->nullable();
            $t->string('status')->default('draft'); $t->unsignedInteger('version')->default(1);
            $t->timestamps(); $t->unique(['cycle_id','code']);
        });
        Schema::create('rka_journal_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->string('source'); $t->string('fingerprint', 64);
            $t->string('status')->default('staged'); $t->unsignedInteger('row_count')->default(0);
            $t->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $t->json('metadata')->nullable(); $t->timestamps();
            $t->unique(['organization_id','source','fingerprint'], 'rka_batch_unique');
        });
        Schema::create('rka_journal_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('batch_id')->constrained('rka_journal_batches')->cascadeOnDelete();
            $t->foreignId('unit_id')->nullable()->constrained('rka_units')->nullOnDelete();
            $t->string('source'); $t->string('external_line_id');
            $t->string('journal_no'); $t->date('posted_on'); $t->string('account_code');
            $t->decimal('debit', 20, 2)->default(0); $t->decimal('credit', 20, 2)->default(0);
            $t->string('entry_kind')->default('unmapped');
            $t->string('posting_status')->default('posted');
            $t->string('related_reference')->nullable(); $t->json('metadata')->nullable();
            $t->timestamps();
            $t->unique(['organization_id','source','external_line_id'], 'rka_journal_source_unique');
        });
        Schema::create('rka_questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->string('code'); $t->string('domain')->default('general'); $t->text('prompt');
            $t->string('answer_type')->default('long_text'); $t->boolean('required')->default(true);
            $t->string('owner_role')->default('reviewer'); $t->json('rules')->nullable();
            $t->json('evidence_policy')->nullable(); $t->unsignedInteger('version')->default(1);
            $t->boolean('active')->default(true); $t->timestamps();
            $t->unique(['organization_id','code','version']);
        });
        Schema::create('rka_question_instances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('cycle_id')->constrained('rka_cycles')->cascadeOnDelete();
            $t->foreignId('question_id')->constrained('rka_questions')->restrictOnDelete();
            $t->foreignId('unit_id')->nullable()->constrained('rka_units')->nullOnDelete();
            $t->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $t->string('context_reference')->nullable(); $t->string('status')->default('open');
            $t->text('prompt_snapshot'); $t->json('rules_snapshot')->nullable();
            $t->timestamp('due_at')->nullable(); $t->timestamps();
            $t->index(['organization_id','cycle_id','status'], 'rka_inst_lookup');
        });
        Schema::create('rka_invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('instance_id')->constrained('rka_question_instances')->cascadeOnDelete();
            $t->foreignId('invited_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $t->string('status')->default('invited'); $t->json('scope')->nullable();
            $t->timestamp('expires_at'); $t->timestamp('accepted_at')->nullable();
            $t->timestamps(); $t->unique(['instance_id','invited_user_id']);
        });
        Schema::create('rka_answers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('instance_id')->constrained('rka_question_instances')->cascadeOnDelete();
            $t->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('revision'); $t->longText('content');
            $t->json('evidence')->nullable(); $t->string('status')->default('submitted');
            $t->timestamps(); $t->unique(['instance_id','author_id','revision'], 'rka_answer_revision_unique');
        });
        Schema::create('rka_narrative_conflicts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('instance_id')->constrained('rka_question_instances')->cascadeOnDelete();
            $t->string('kind'); $t->text('description');
            $t->string('status')->default('open'); $t->text('resolution')->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('rka_syntheses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('instance_id')->constrained('rka_question_instances')->cascadeOnDelete();
            $t->unsignedInteger('version'); $t->longText('content');
            $t->json('source_answer_ids'); $t->string('status')->default('pending_review');
            $t->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable(); $t->timestamps();
            $t->unique(['instance_id','version']);
        });
        Schema::create('rka_approval_policies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->string('action'); $t->json('conditions')->nullable();
            $t->json('steps'); $t->unsignedInteger('priority')->default(100);
            $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('rka_approval_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->string('action'); $t->string('subject_type'); $t->unsignedBigInteger('subject_id');
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->string('status')->default('pending'); $t->string('risk')->default('low');
            $t->json('steps_snapshot'); $t->unsignedInteger('current_step')->default(0);
            $t->timestamps(); $t->index(['organization_id','status']);
        });
        Schema::create('rka_approval_decisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('request_id')->constrained('rka_approval_requests')->cascadeOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('step'); $t->string('decision'); $t->text('reason')->nullable();
            $t->timestamps();
        });
        Schema::create('rka_book_templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->string('name'); $t->unsignedInteger('version')->default(1);
            $t->json('sections'); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('rka_books', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('cycle_id')->constrained('rka_cycles')->cascadeOnDelete();
            $t->foreignId('template_id')->constrained('rka_book_templates')->restrictOnDelete();
            $t->string('title'); $t->string('status')->default('draft');
            $t->json('snapshot')->nullable(); $t->timestamp('published_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('rka_book_sections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('book_id')->constrained('rka_books')->cascadeOnDelete();
            $t->string('code'); $t->string('title'); $t->unsignedInteger('position')->default(0);
            $t->longText('content')->nullable(); $t->boolean('required')->default(false);
            $t->string('status')->default('draft'); $t->json('source_references')->nullable();
            $t->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps(); $t->unique(['book_id','code']);
        });
        Schema::create('rka_audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action'); $t->string('subject_type'); $t->unsignedBigInteger('subject_id');
            $t->json('payload')->nullable(); $t->timestamp('created_at')->useCurrent();
            $t->index(['organization_id','subject_type','subject_id'], 'rka_audit_subject');
        });
    }
    public function down(): void
    {
        foreach ([
            'rka_audit_events','rka_book_sections','rka_books','rka_book_templates',
            'rka_approval_decisions','rka_approval_requests','rka_approval_policies',
            'rka_syntheses','rka_narrative_conflicts','rka_answers','rka_invitations',
            'rka_question_instances','rka_questions','rka_journal_lines','rka_journal_batches',
            'rka_shared_pools','rka_budget_lines','rka_cycles','rka_units',
            'rka_memberships','rka_organizations'
        ] as $name) Schema::dropIfExists($name);
    }
};
