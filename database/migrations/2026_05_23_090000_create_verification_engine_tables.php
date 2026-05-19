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
            if (! Schema::hasColumn('users', 'current_verification_level')) {
                $table->unsignedTinyInteger('current_verification_level')->default(0)->after('kyc_tier')->index();
            }
            if (! Schema::hasColumn('users', 'verification_level_override')) {
                $table->unsignedTinyInteger('verification_level_override')->nullable()->after('current_verification_level')->index();
            }
            if (! Schema::hasColumn('users', 'verification_level_override_reason')) {
                $table->text('verification_level_override_reason')->nullable()->after('verification_level_override');
            }
            if (! Schema::hasColumn('users', 'verification_level_overridden_by')) {
                $table->foreignId('verification_level_overridden_by')->nullable()->after('verification_level_override_reason')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'verification_level_overridden_at')) {
                $table->timestamp('verification_level_overridden_at')->nullable()->after('verification_level_overridden_by');
            }
            if (! Schema::hasColumn('users', 'custom_client_post_limit_minor')) {
                $table->unsignedBigInteger('custom_client_post_limit_minor')->nullable()->after('verification_level_overridden_at');
            }
            if (! Schema::hasColumn('users', 'custom_freelancer_proposal_limit_minor')) {
                $table->unsignedBigInteger('custom_freelancer_proposal_limit_minor')->nullable()->after('custom_client_post_limit_minor');
            }
            if (! Schema::hasColumn('users', 'verification_restricted_at')) {
                $table->timestamp('verification_restricted_at')->nullable()->after('custom_freelancer_proposal_limit_minor')->index();
            }
            if (! Schema::hasColumn('users', 'verification_restriction_reason')) {
                $table->text('verification_restriction_reason')->nullable()->after('verification_restricted_at');
            }
        });

        Schema::table('user_verifications', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_verifications', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('user_verifications', 'verification_type')) {
                $table->string('verification_type', 64)->nullable()->after('category')->index();
            }
            if (! Schema::hasColumn('user_verifications', 'encrypted_identifier')) {
                $table->text('encrypted_identifier')->nullable()->after('metadata');
            }
        });

        Schema::create('verification_engine_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('affected_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 120)->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['affected_user_id', 'created_at']);
        });

        Schema::create('verification_anomaly_flags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('quest_id')->nullable()->constrained('quests')->nullOnDelete();
            $table->foreignId('quest_offer_id')->nullable()->constrained('quest_offers')->nullOnDelete();
            $table->string('type', 100)->index();
            $table->string('status', 32)->default('open')->index();
            $table->string('severity', 24)->default('medium')->index();
            $table->json('context')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'type']);
        });

        Schema::create('quest_arbitration_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quest_id')->constrained('quests')->cascadeOnDelete();
            $table->foreignId('quest_offer_id')->nullable()->constrained('quest_offers')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('party', 32)->index();
            $table->timestamp('agreed_at')->index();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->unique(['quest_id', 'quest_offer_id', 'user_id', 'party'], 'quest_arbitration_unique_party');
        });

        $now = now();
        foreach ([
            'verification_types' => config('verification_engine.types'),
            'verification_level_requirements' => config('verification_engine.levels'),
            'verification_limits' => config('verification_engine.limits'),
            'verification_safeguards' => config('verification_engine.safeguards'),
        ] as $key => $value) {
            DB::table('kyc_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value), 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_arbitration_agreements');
        Schema::dropIfExists('verification_anomaly_flags');
        Schema::dropIfExists('verification_engine_audit_logs');

        Schema::table('user_verifications', function (Blueprint $table): void {
            if (Schema::hasColumn('user_verifications', 'submitted_by')) {
                $table->dropConstrainedForeignId('submitted_by');
            }
            foreach (['encrypted_identifier', 'verification_type', 'submitted_by'] as $column) {
                if (Schema::hasColumn('user_verifications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'verification_level_overridden_by')) {
                $table->dropConstrainedForeignId('verification_level_overridden_by');
            }
            foreach ([
                'verification_restriction_reason',
                'verification_restricted_at',
                'custom_freelancer_proposal_limit_minor',
                'custom_client_post_limit_minor',
                'verification_level_overridden_at',
                'verification_level_override_reason',
                'verification_level_override',
                'current_verification_level',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
