<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_patrol_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quest_contract_id')->constrained('quest_contracts')->cascadeOnDelete();
            $table->string('flag_type', 64);
            $table->string('severity', 16)->default('medium');
            $table->string('status', 24)->default('open');
            $table->string('fingerprint', 160)->unique();
            $table->text('summary')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('detected_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dismissed_at')->nullable();
            $table->foreignId('dismissed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('dismissal_reason')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['quest_contract_id', 'status'], 'cpf_contract_status_idx');
            $table->index(['flag_type', 'status'], 'cpf_type_status_idx');
            $table->index(['severity', 'status'], 'cpf_severity_status_idx');
            $table->index('detected_at', 'cpf_detected_idx');
        });

        Schema::create('contract_saved_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('filters');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'name'], 'contract_saved_filters_user_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_saved_filters');
        Schema::dropIfExists('contract_patrol_flags');
    }
};
