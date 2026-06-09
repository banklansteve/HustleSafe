<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activity_patrol_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('anomaly_type', 64);
            $table->string('risk_level', 16)->default('medium');
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->string('status', 24)->default('open');
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('fingerprint', 128)->unique();
            $table->string('summary', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'risk_level', 'detected_at'], 'uapf_status_risk_detected_idx');
            $table->index(['user_id', 'status'], 'uapf_user_status_idx');
            $table->index(['assigned_to_id', 'status'], 'uapf_assigned_status_idx');
        });

        Schema::create('user_activity_patrol_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('flag_id')->nullable();
            $table->string('action_type', 64);
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason_code', 64)->nullable();
            $table->text('reason_notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['user_id', 'occurred_at'], 'uapa_user_occurred_idx');
        });

        Schema::create('user_activity_patrol_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('flag_id')->nullable();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'uapn_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_patrol_notes');
        Schema::dropIfExists('user_activity_patrol_actions');
        Schema::dropIfExists('user_activity_patrol_flags');
    }
};
