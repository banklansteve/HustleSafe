<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('freelancer_subscriptions')) {
            Schema::create('freelancer_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('status', 32)->default('active');
                $table->string('tier', 16)->default('free');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('renewal_date')->nullable();
                $table->string('billing_cycle', 16)->nullable();
                $table->unsignedBigInteger('monthly_price_minor')->default(0);
                $table->unsignedBigInteger('annual_price_minor')->default(0);
                $table->boolean('auto_renew')->default(false);
                $table->json('payment_method_snapshot')->nullable();
                $table->unsignedBigInteger('total_spent_minor')->default(0);
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status'], 'fs_user_status_idx');
            });
        }

        if (! Schema::hasTable('freelancer_subscription_payments')) {
            Schema::create('freelancer_subscription_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('freelancer_subscription_id');
                $table->foreign('freelancer_subscription_id', 'fsp_subscription_fk')
                    ->references('id')->on('freelancer_subscriptions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('amount_minor');
                $table->string('billing_cycle', 16);
                $table->string('paystack_reference')->unique();
                $table->string('status', 32)->default('pending');
                $table->timestamp('paid_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('freelancer_subscription_histories')) {
            Schema::create('freelancer_subscription_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('freelancer_subscription_id');
                $table->foreign('freelancer_subscription_id', 'fsh_subscription_fk')
                    ->references('id')->on('freelancer_subscriptions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('event', 64);
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32)->nullable();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('meta')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();

                $table->index(['freelancer_subscription_id', 'occurred_at'], 'fsh_sub_occurred_idx');
            });
        }

        if (! Schema::hasTable('proposal_quota_usages')) {
            Schema::create('proposal_quota_usages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
                $table->char('month', 7);
                $table->unsignedSmallInteger('proposals_count')->default(0);
                $table->string('plan_tier', 16)->default('free');
                $table->timestamps();

                $table->unique(['freelancer_id', 'month'], 'pqu_freelancer_month_uq');
            });
        }

        if (! Schema::hasTable('proposal_quota_audit_logs')) {
            Schema::create('proposal_quota_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
                $table->char('month', 7);
                $table->string('plan_tier', 16);
                $table->unsignedSmallInteger('proposals_used');
                $table->unsignedSmallInteger('quota_limit')->nullable();
                $table->string('result', 32);
                $table->foreignId('quest_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamp('occurred_at');
                $table->timestamps();

                $table->index(['freelancer_id', 'occurred_at'], 'pqa_freelancer_occurred_idx');
            });
        }

        if (! Schema::hasTable('quest_boosts')) {
            Schema::create('quest_boosts', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 32)->unique();
                $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
                $table->string('quest_title_snapshot');
                $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('granted_by_admin_id')->constrained('users')->cascadeOnDelete();
                $table->string('tier', 16);
                $table->unsignedBigInteger('planned_cost_minor');
                $table->string('status', 32)->default('active');
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->text('grant_reason');
                $table->timestamp('granted_at');
                $table->timestamp('actual_ended_at')->nullable();
                $table->string('visibility_tier', 16)->default('tier_1');
                $table->timestamps();

                $table->index(['status', 'ends_at'], 'qb_status_ends_idx');
                $table->index(['quest_id', 'status'], 'qb_quest_status_idx');
            });
        }

        if (! Schema::hasTable('quest_boost_audit_logs')) {
            Schema::create('quest_boost_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quest_boost_id')->constrained()->cascadeOnDelete();
                $table->string('action_type', 64);
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('reason')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();

                $table->index(['quest_boost_id', 'occurred_at'], 'qba_boost_occurred_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_boost_audit_logs');
        Schema::dropIfExists('quest_boosts');
        Schema::dropIfExists('proposal_quota_audit_logs');
        Schema::dropIfExists('proposal_quota_usages');
        Schema::dropIfExists('freelancer_subscription_histories');
        Schema::dropIfExists('freelancer_subscription_payments');
        Schema::dropIfExists('freelancer_subscriptions');
    }
};
