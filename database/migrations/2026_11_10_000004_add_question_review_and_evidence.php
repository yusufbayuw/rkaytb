<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rka_question_instances', function (Blueprint $t) {
            $t->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('rka_evidence_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('instance_id')->constrained('rka_question_instances')->cascadeOnDelete();
            $t->foreignId('answer_id')->constrained('rka_answers')->cascadeOnDelete();
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->string('original_name', 255);
            $t->string('storage_path', 450);
            $t->string('mime_type', 120);
            $t->string('sha256', 64);
            $t->string('status')->default('pending');
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->text('review_note')->nullable();
            $t->timestamps();
            $t->index(['organization_id', 'instance_id', 'status'], 'rka_evidence_review_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rka_evidence_records');
        Schema::table('rka_question_instances', function (Blueprint $t) {
            $t->dropConstrainedForeignId('reviewer_user_id');
        });
    }
};
