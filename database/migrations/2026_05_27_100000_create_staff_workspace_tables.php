<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_notification_preferences')) {
            Schema::create('staff_notification_preferences', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('staff_user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->json('preferences')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff_watchlist_items')) {
            Schema::create('staff_watchlist_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('watchable_type');
                $table->unsignedBigInteger('watchable_id');
                $table->string('label')->nullable();
                $table->text('notes')->nullable();
                $table->string('priority', 24)->default('medium')->index();
                $table->timestamps();

                $table->unique(['staff_user_id', 'watchable_type', 'watchable_id'], 'staff_watchlist_unique');
                $table->index(['watchable_type', 'watchable_id']);
            });
        }

        if (! Schema::hasTable('staff_freelancer_quality_flags')) {
            Schema::create('staff_freelancer_quality_flags', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 32)->default('open')->index();
                $table->string('trigger_reason', 80)->index();
                $table->json('metrics_snapshot')->nullable();
                $table->json('trend_snapshot')->nullable();
                $table->text('staff_notes')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff_payment_exceptions')) {
            Schema::create('staff_payment_exceptions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('quest_id')->nullable()->constrained('quests')->nullOnDelete();
                $table->foreignId('admin_task_id')->nullable()->constrained('admin_tasks')->nullOnDelete();
                $table->string('type', 48)->index();
                $table->string('status', 32)->default('open')->index();
                $table->unsignedBigInteger('amount_minor')->nullable();
                $table->string('error_code', 80)->nullable();
                $table->text('error_summary')->nullable();
                $table->text('staff_summary')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff_patrol_sessions')) {
            Schema::create('staff_patrol_sessions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('content_type', 32)->index();
                $table->unsignedBigInteger('category_id')->nullable()->index();
                $table->date('date_from')->nullable();
                $table->date('date_to')->nullable();
                $table->unsignedSmallInteger('sample_size')->default(25);
                $table->unsignedSmallInteger('reviewed_count')->default(0);
                $table->string('status', 24)->default('active')->index();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff_patrol_items')) {
            Schema::create('staff_patrol_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('staff_patrol_session_id')->constrained('staff_patrol_sessions')->cascadeOnDelete();
                $table->string('reviewable_type');
                $table->unsignedBigInteger('reviewable_id');
                $table->string('decision', 32)->nullable()->index();
                $table->text('notes')->nullable();
                $table->json('risk_signals')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->unique(['staff_patrol_session_id', 'reviewable_type', 'reviewable_id'], 'staff_patrol_item_unique');
            });
        }

        if (! Schema::hasTable('staff_onboarding_outreach')) {
            Schema::create('staff_onboarding_outreach', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('scenario', 64)->index();
                $table->string('status', 32)->default('pending')->index();
                $table->string('friction_point', 120)->nullable();
                $table->json('context')->nullable();
                $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('contacted_by_staff_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('contacted_at')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'scenario']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_onboarding_outreach');
        Schema::dropIfExists('staff_patrol_items');
        Schema::dropIfExists('staff_patrol_sessions');
        Schema::dropIfExists('staff_payment_exceptions');
        Schema::dropIfExists('staff_freelancer_quality_flags');
        Schema::dropIfExists('staff_watchlist_items');
        Schema::dropIfExists('staff_notification_preferences');
    }
};
