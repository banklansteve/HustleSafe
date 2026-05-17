<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'referral_code')) {
                $table->string('referral_code', 24)->nullable()->unique()->after('uid');
            }
            if (! Schema::hasColumn('users', 'referred_by_user_id')) {
                $table->foreignId('referred_by_user_id')->nullable()->after('referral_code')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'referral_program_blocked_at')) {
                $table->timestamp('referral_program_blocked_at')->nullable()->after('referred_by_user_id')->index();
            }
        });

        Schema::create('featured_quest_listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quest_id')->constrained('quests')->cascadeOnDelete();
            $table->foreignId('client_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tier', 24)->index();
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('starts_at')->index();
            $table->timestamp('expires_at')->index();
            $table->unsignedInteger('amount_paid_minor')->default(0);
            $table->unsignedInteger('proposal_views_count')->default(0);
            $table->unsignedInteger('notifications_sent_count')->default(0);
            $table->boolean('homepage_carousel')->default(false);
            $table->boolean('weekly_digest')->default(false);
            $table->boolean('social_post_required')->default(false);
            $table->timestamp('social_post_handled_at')->nullable();
            $table->text('manual_grant_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedInteger('refund_amount_minor')->default(0);
            $table->timestamps();

            $table->index(['quest_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('promotion_coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('status', 24)->default('active')->index();
            $table->string('discount_type', 24);
            $table->unsignedInteger('discount_value_minor')->default(0);
            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->unsignedInteger('max_discount_minor')->nullable();
            $table->string('applies_to', 40)->default('all')->index();
            $table->foreignId('quest_category_id')->nullable()->constrained('quest_categories')->nullOnDelete();
            $table->string('eligibility', 40)->default('all')->index();
            $table->json('eligible_user_ids')->nullable();
            $table->unsignedInteger('usage_limit_total')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->unsignedInteger('minimum_transaction_minor')->default(0);
            $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('promotion_coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_coupon_id')->constrained('promotion_coupons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->nullableMorphs('redeemable');
            $table->string('payment_type', 40)->index();
            $table->unsignedInteger('transaction_amount_minor');
            $table->unsignedInteger('discount_amount_minor');
            $table->unsignedInteger('net_amount_minor');
            $table->string('ip_address', 64)->nullable();
            $table->string('device_fingerprint')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('promotion_coupon_fraud_flags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_coupon_id')->nullable()->constrained('promotion_coupons')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 80)->index();
            $table->string('status', 24)->default('open')->index();
            $table->json('evidence')->nullable();
            $table->timestamps();
        });

        Schema::create('user_referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('referral_code', 24)->index();
            $table->string('status', 32)->default('signed_up')->index();
            $table->string('qualifying_event', 80)->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('device_fingerprint')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('referred_user_id');
        });

        Schema::create('referral_rewards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_referral_id')->constrained('user_referrals')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reward_type', 32)->index();
            $table->unsignedInteger('amount_minor')->default(0);
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('referral_abuse_flags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_referral_id')->nullable()->constrained('user_referrals')->nullOnDelete();
            $table->foreignId('referrer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 80)->index();
            $table->string('status', 24)->default('open')->index();
            $table->json('evidence')->nullable();
            $table->timestamps();
        });

        Schema::create('promotion_badges', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->json('criteria')->nullable();
            $table->boolean('is_automatic')->default(false)->index();
            $table->boolean('requires_manual_review')->default(false);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_time_limited')->default(false);
            $table->unsignedSmallInteger('display_order')->default(0)->index();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('promotion_badge_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_badge_id')->constrained('promotion_badges')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('awarded_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('justification')->nullable();
            $table->timestamp('awarded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();

            $table->unique(['promotion_badge_id', 'user_id']);
        });

        Schema::create('promotion_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        DB::table('promotion_settings')->insert([
            ['key' => 'featured_tiers', 'value' => json_encode([
                'standard' => ['label' => 'Standard Boost', 'durations' => [3, 7], 'prices_minor' => [3 => 250000, 7 => 500000], 'placements' => ['category_top', 'featured_badge']],
                'premium' => ['label' => 'Premium Boost', 'durations' => [7, 14], 'prices_minor' => [7 => 1200000, 14 => 2200000], 'placements' => ['category_top', 'homepage_carousel', 'push_notification']],
                'elite' => ['label' => 'Elite Boost', 'durations' => [14, 30], 'prices_minor' => [14 => 3500000, 30 => 6500000], 'placements' => ['category_top', 'homepage_carousel', 'push_notification', 'weekly_digest', 'social_post']],
            ]), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'referral_program', 'value' => json_encode(['reward_type' => 'wallet_credit', 'client_reward_minor' => 250000, 'freelancer_reward_minor' => 150000, 'qualifying_event' => 'first_transaction', 'reward_expiry_days' => 90]), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $badges = [
            ['Top Rated', 'top-rated', 'star', '4.8+ rating across 10 completed contracts in 90 days, with no active disputes.', true],
            ['Rising Talent', 'rising-talent', 'sparkles', 'Joined within 6 months, completed 3 contracts, 5-star average, zero disputes.', true],
            ['Expert Verified', 'expert-verified', 'academic-cap', 'Passed category skill verification with 90% or higher.', true],
            ['Quest Champion', 'quest-champion', 'trophy', 'Client with 10+ quests and 4.5+ freelancer satisfaction.', true],
            ['Verified Pro', 'verified-pro', 'shield-check', 'Full KYC verification achieved.', true],
            ['Verified Business', 'verified-business', 'building-office', 'CAC business verification confirmed.', true],
            ['Fast Responder', 'fast-responder', 'bolt', 'Average proposal response under 4 hours across 20 proposals.', true],
            ['Long-term Partner', 'long-term-partner', 'heart', 'Active for 12+ months with trust score above 70.', true],
        ];

        foreach ($badges as $i => [$name, $slug, $icon, $description, $automatic]) {
            DB::table('promotion_badges')->insert([
                'name' => $name,
                'slug' => $slug,
                'icon' => $icon,
                'description' => $description,
                'criteria' => json_encode(['standard' => $description]),
                'is_automatic' => $automatic,
                'requires_manual_review' => false,
                'is_public' => true,
                'display_order' => $i + 1,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_settings');
        Schema::dropIfExists('promotion_badge_user');
        Schema::dropIfExists('promotion_badges');
        Schema::dropIfExists('referral_abuse_flags');
        Schema::dropIfExists('referral_rewards');
        Schema::dropIfExists('user_referrals');
        Schema::dropIfExists('promotion_coupon_fraud_flags');
        Schema::dropIfExists('promotion_coupon_redemptions');
        Schema::dropIfExists('promotion_coupons');
        Schema::dropIfExists('featured_quest_listings');

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'referral_program_blocked_at')) {
                $table->dropColumn('referral_program_blocked_at');
            }
            if (Schema::hasColumn('users', 'referred_by_user_id')) {
                $table->dropConstrainedForeignId('referred_by_user_id');
            }
            if (Schema::hasColumn('users', 'referral_code')) {
                $table->dropColumn('referral_code');
            }
        });
    }
};
