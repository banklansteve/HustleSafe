<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('premium_patrol_flags', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('flag_type', 64);
            $table->string('severity', 16)->default('medium');
            $table->string('status', 24)->default('open');
            $table->string('fingerprint', 128)->unique();
            $table->json('meta')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('auto_resolve_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->foreignId('dismissed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('dismissal_reason')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'status'], 'ppf_subject_status_idx');
            $table->index(['flag_type', 'status'], 'ppf_type_status_idx');
            $table->index(['detected_at'], 'ppf_detected_idx');
        });

        Schema::create('premium_patrol_investigations', function (Blueprint $table) {
            $table->id();
            $table->string('case_reference', 32)->unique();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->string('status', 32)->default('pending');
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('opened_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->json('timeline')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], 'ppi_subject_idx');
            $table->index(['status'], 'ppi_status_idx');
        });

        Schema::create('premium_patrol_watchlist', function (Blueprint $table) {
            $table->id();
            $table->string('watchlist_type', 24);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->foreignId('added_by_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['watchlist_type', 'user_id', 'expires_at'], 'ppw_type_user_exp_idx');
        });

        Schema::create('premium_patrol_actions', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->string('action_type', 64);
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason_code', 64)->nullable();
            $table->text('reason_notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'occurred_at'], 'ppa_subject_occurred_idx');
        });

        if (Schema::hasTable('freelancer_subscriptions')) {
            Schema::table('freelancer_subscriptions', function (Blueprint $table) {
                if (! Schema::hasColumn('freelancer_subscriptions', 'admin_suspended_at')) {
                    $table->timestamp('admin_suspended_at')->nullable()->after('cancellation_reason');
                    $table->foreignId('admin_suspended_by_id')->nullable()->after('admin_suspended_at')->constrained('users')->nullOnDelete();
                    $table->text('admin_suspension_reason')->nullable()->after('admin_suspended_by_id');
                    $table->timestamp('manual_review_until')->nullable()->after('admin_suspension_reason');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('freelancer_subscriptions')) {
            Schema::table('freelancer_subscriptions', function (Blueprint $table) {
                if (Schema::hasColumn('freelancer_subscriptions', 'admin_suspended_by_id')) {
                    $table->dropConstrainedForeignId('admin_suspended_by_id');
                }
                foreach (['admin_suspended_at', 'admin_suspension_reason', 'manual_review_until'] as $col) {
                    if (Schema::hasColumn('freelancer_subscriptions', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('premium_patrol_actions');
        Schema::dropIfExists('premium_patrol_watchlist');
        Schema::dropIfExists('premium_patrol_investigations');
        Schema::dropIfExists('premium_patrol_flags');
    }
};
