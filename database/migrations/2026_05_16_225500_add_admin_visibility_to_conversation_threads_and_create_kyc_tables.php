<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quest_conversation_threads', function (Blueprint $table): void {
            if (! Schema::hasColumn('quest_conversation_threads', 'admin_hidden_at')) {
                $table->timestamp('admin_hidden_at')->nullable()->after('client_last_read_at')->index();
            }
            if (! Schema::hasColumn('quest_conversation_threads', 'admin_deleted_at')) {
                $table->timestamp('admin_deleted_at')->nullable()->after('admin_hidden_at')->index();
            }
            if (! Schema::hasColumn('quest_conversation_threads', 'admin_visibility_reason')) {
                $table->text('admin_visibility_reason')->nullable()->after('admin_deleted_at');
            }
            if (! Schema::hasColumn('quest_conversation_threads', 'admin_visibility_changed_by')) {
                $table->foreignId('admin_visibility_changed_by')->nullable()->after('admin_visibility_reason')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at')->index();
            }
            if (! Schema::hasColumn('users', 'kyc_tier')) {
                $table->unsignedTinyInteger('kyc_tier')->default(0)->after('verification_tier')->index();
            }
            if (! Schema::hasColumn('users', 'kyc_status')) {
                $table->string('kyc_status', 32)->default('unverified')->after('kyc_tier')->index();
            }
            if (! Schema::hasColumn('users', 'kyc_verified_at')) {
                $table->timestamp('kyc_verified_at')->nullable()->after('kyc_status');
            }
        });

        Schema::table('user_verifications', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_verifications', 'target_tier')) {
                $table->unsignedTinyInteger('target_tier')->nullable()->after('category')->index();
            }
            if (! Schema::hasColumn('user_verifications', 'provider')) {
                $table->string('provider', 40)->nullable()->after('status');
            }
            if (! Schema::hasColumn('user_verifications', 'provider_reference')) {
                $table->string('provider_reference')->nullable()->after('provider');
            }
            if (! Schema::hasColumn('user_verifications', 'provider_response')) {
                $table->json('provider_response')->nullable()->after('metadata');
            }
            if (! Schema::hasColumn('user_verifications', 'confidence_score')) {
                $table->unsignedTinyInteger('confidence_score')->nullable()->after('provider_response');
            }
            if (! Schema::hasColumn('user_verifications', 'queue_reason')) {
                $table->string('queue_reason', 80)->nullable()->after('confidence_score')->index();
            }
            if (! Schema::hasColumn('user_verifications', 'attempt_count')) {
                $table->unsignedTinyInteger('attempt_count')->default(1)->after('queue_reason');
            }
        });

        Schema::create('kyc_review_cases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_verification_id')->nullable()->constrained('user_verifications')->nullOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('target_tier')->index();
            $table->string('verification_type', 40)->index();
            $table->string('status', 32)->default('pending')->index();
            $table->string('priority', 24)->default('standard')->index();
            $table->string('queue_reason', 80)->index();
            $table->unsignedTinyInteger('confidence_score')->nullable();
            $table->json('submitted_snapshot')->nullable();
            $table->json('provider_snapshot')->nullable();
            $table->json('comparison')->nullable();
            $table->timestamp('entered_queue_at')->index();
            $table->timestamp('review_started_at')->nullable();
            $table->timestamp('decided_at')->nullable()->index();
            $table->string('decision')->nullable()->index();
            $table->string('decision_reason')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority', 'entered_queue_at']);
        });

        Schema::create('kyc_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kyc_review_case_id')->constrained('kyc_review_cases')->cascadeOnDelete();
            $table->string('label');
            $table->string('document_type', 40)->default('supporting_document');
            $table->string('disk')->default('local');
            $table->string('path', 1000);
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('kyc_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kyc_review_case_id')->constrained('kyc_review_cases')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 60)->index();
            $table->string('reason_code', 80)->index();
            $table->text('note')->nullable();
            $table->json('correction_fields')->nullable();
            $table->json('portfolio_scores')->nullable();
            $table->unsignedInteger('time_to_decision_seconds')->default(0);
            $table->timestamps();
        });

        Schema::create('kyc_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kyc_review_case_id')->nullable()->constrained('kyc_review_cases')->nullOnDelete();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event', 80)->index();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('kyc_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        DB::table('kyc_settings')->insert([
            ['key' => 'active_provider', 'value' => json_encode('manual'), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'fallback_provider', 'value' => json_encode(null), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'thresholds', 'value' => json_encode(['nin' => 85, 'bvn' => 85, 'face_similarity' => 85]), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'feature_gates', 'value' => json_encode(['browse' => 0, 'submit_proposal' => 1, 'post_quest' => 1, 'high_value_quest' => 2, 'withdraw_large_amount' => 4, 'business_badge' => 5]), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'resubmission_limit', 'value' => json_encode(3), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'verification_fees', 'value' => json_encode(['enabled' => false, 'cac_fee_minor' => 0]), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'limits', 'value' => json_encode(['tier_1_client_quest_minor' => 25000000, 'tier_2_client_quest_minor' => 100000000, 'tier_4_single_withdrawal_minor' => 500000000]), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_settings');
        Schema::dropIfExists('kyc_audit_events');
        Schema::dropIfExists('kyc_decisions');
        Schema::dropIfExists('kyc_documents');
        Schema::dropIfExists('kyc_review_cases');

        Schema::table('user_verifications', function (Blueprint $table): void {
            foreach (['attempt_count', 'queue_reason', 'confidence_score', 'provider_response', 'provider_reference', 'provider', 'target_tier'] as $column) {
                if (Schema::hasColumn('user_verifications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            foreach (['kyc_verified_at', 'kyc_status', 'kyc_tier', 'phone_verified_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('quest_conversation_threads', function (Blueprint $table): void {
            if (Schema::hasColumn('quest_conversation_threads', 'admin_visibility_changed_by')) {
                $table->dropConstrainedForeignId('admin_visibility_changed_by');
            }
            foreach (['admin_visibility_reason', 'admin_deleted_at', 'admin_hidden_at'] as $column) {
                if (Schema::hasColumn('quest_conversation_threads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
