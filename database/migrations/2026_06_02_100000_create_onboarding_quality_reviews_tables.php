<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('onboarding_quality_reviews')) {
            return;
        }

        Schema::create('onboarding_quality_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('user_type', 20)->index();
            $table->string('status', 40)->default('pending')->index();
            $table->unsignedTinyInteger('completeness_score')->default(0);
            $table->json('auto_flags')->nullable();
            $table->json('manual_flag_overrides')->nullable();
            $table->boolean('monitoring_flagged')->default(false)->index();
            $table->text('monitoring_reason')->nullable();
            $table->timestamp('review_deadline_at')->nullable()->index();
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_action_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_quality_reviews');
    }
};
