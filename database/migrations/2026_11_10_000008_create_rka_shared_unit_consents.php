<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rka_shared_pool_unit_consents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('rka_organizations')->cascadeOnDelete();
            $t->foreignId('cycle_id')->constrained('rka_cycles')->cascadeOnDelete();
            $t->foreignId('pool_id')->constrained('rka_shared_pools')->cascadeOnDelete();
            $t->foreignId('unit_id')->constrained('rka_units')->restrictOnDelete();
            $t->unsignedInteger('pool_version');
            $t->string('allocation_hash', 64);
            $t->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('approved_at');
            $t->timestamps();
            $t->unique(['pool_id', 'unit_id', 'pool_version'], 'rka_pool_unit_consent_version');
            $t->index(['organization_id', 'cycle_id', 'pool_id'], 'rka_pool_consent_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rka_shared_pool_unit_consents');
    }
};
