<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_cases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('moderatable_type');
            $table->unsignedBigInteger('moderatable_id');
            $table->index(['moderatable_type', 'moderatable_id'], 'moderation_cases_moderatable_idx');
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('content_type', 40)->index();
            $table->string('queue', 40)->index();
            $table->string('status', 32)->default('open')->index();
            $table->string('severity', 24)->default('warning')->index();
            $table->string('visibility_state', 40)->default('live_under_review')->index();
            $table->string('source', 40)->default('automated')->index();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->string('title')->nullable();
            $table->text('excerpt')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamp('entered_queue_at')->index();
            $table->timestamp('review_started_at')->nullable();
            $table->timestamp('decided_at')->nullable()->index();
            $table->string('decision')->nullable()->index();
            $table->string('decision_reason')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['queue', 'status', 'entered_queue_at']);
        });

        Schema::create('moderation_case_triggers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('moderation_case_id')->constrained('moderation_cases')->cascadeOnDelete();
            $table->string('rule_key', 80)->index();
            $table->string('rule_type', 40)->index();
            $table->string('category', 80)->nullable()->index();
            $table->string('severity', 24)->default('warning')->index();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->string('matched_text', 500)->nullable();
            $table->text('context')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('moderation_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('moderation_case_id')->constrained('moderation_cases')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 60)->index();
            $table->string('reason_code', 80)->index();
            $table->text('note')->nullable();
            $table->json('edited_snapshot')->nullable();
            $table->unsignedInteger('time_to_decision_seconds')->default(0);
            $table->timestamps();
        });

        Schema::create('moderation_keywords', function (Blueprint $table): void {
            $table->id();
            $table->string('phrase');
            $table->string('severity', 24)->default('warning')->index();
            $table->string('category', 80)->default('policy_violation')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('moderation_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('moderation_notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('moderation_appeals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('moderation_case_id')->constrained('moderation_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('open')->index();
            $table->text('statement');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('content_reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('content_reports', 'moderation_case_id')) {
                $table->foreignId('moderation_case_id')->nullable()->after('id')->constrained('moderation_cases')->nullOnDelete();
            }
        });

        DB::table('moderation_settings')->insert([
            ['key' => 'new_account_review_hours', 'value' => json_encode(48), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'allowed_external_domains', 'value' => json_encode(['linkedin.com', 'github.com', 'behance.net', 'dribbble.com']), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'cloudinary_moderation_enabled', 'value' => json_encode(true), 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('moderation_keywords')->insert([
            ['phrase' => 'whatsapp me', 'severity' => 'critical', 'category' => 'off_platform_solicitation', 'is_active' => true, 'note' => 'Off-platform contact solicitation.', 'created_at' => now(), 'updated_at' => now()],
            ['phrase' => 'pay via transfer directly', 'severity' => 'critical', 'category' => 'off_platform_payment', 'is_active' => true, 'note' => 'Off-platform payment solicitation.', 'created_at' => now(), 'updated_at' => now()],
            ['phrase' => 'contact me on instagram', 'severity' => 'warning', 'category' => 'off_platform_solicitation', 'is_active' => true, 'note' => 'Off-platform contact solicitation.', 'created_at' => now(), 'updated_at' => now()],
            ['phrase' => 'wire money', 'severity' => 'warning', 'category' => 'fraud_pattern', 'is_active' => true, 'note' => 'Common scam phrase.', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('moderation_notification_templates')->insert([
            ['key' => 'approved_with_warning', 'label' => 'Approved with warning', 'subject' => 'Your content was approved with a note', 'body' => 'Your content is live, but our team noted: {{reason}}', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'removed', 'label' => 'Content removed', 'subject' => 'Your content was removed', 'body' => 'Your content was removed for: {{reason}}. You may appeal this decision from your account.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'revision_requested', 'label' => 'Revision requested', 'subject' => 'Please revise your content', 'body' => 'Please revise your content before it can be approved: {{reason}}', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::table('content_reports', function (Blueprint $table): void {
            if (Schema::hasColumn('content_reports', 'moderation_case_id')) {
                $table->dropConstrainedForeignId('moderation_case_id');
            }
        });

        Schema::dropIfExists('moderation_appeals');
        Schema::dropIfExists('moderation_notification_templates');
        Schema::dropIfExists('moderation_settings');
        Schema::dropIfExists('moderation_keywords');
        Schema::dropIfExists('moderation_decisions');
        Schema::dropIfExists('moderation_case_triggers');
        Schema::dropIfExists('moderation_cases');
    }
};
