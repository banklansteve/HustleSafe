<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_response_templates')) {
            Schema::create('staff_response_templates', function (Blueprint $table): void {
                $table->id();
                $table->string('slug', 80)->unique();
                $table->string('situation_key', 64)->index();
                $table->string('category', 48)->index();
                $table->string('title');
                $table->string('subject');
                $table->text('body');
                $table->json('policy_tags')->nullable();
                $table->json('placeholders')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedSmallInteger('sort_order')->default(100);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff_proactive_outreach_items')) {
            Schema::create('staff_proactive_outreach_items', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('situation_key', 64)->index();
                $table->string('status', 24)->default('open')->index();
                $table->string('priority', 16)->default('medium')->index();
                $table->unsignedSmallInteger('priority_score')->default(50)->index();
                $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('quest_id')->nullable()->constrained('quests')->nullOnDelete();
                $table->foreignId('quest_offer_id')->nullable()->constrained('quest_offers')->nullOnDelete();
                $table->foreignId('quest_dispute_id')->nullable()->constrained('quest_disputes')->nullOnDelete();
                $table->unsignedBigInteger('conversation_thread_review_id')->nullable();
                $table->string('fingerprint', 64)->unique();
                $table->json('context')->nullable();
                $table->string('suggested_template_slug', 80)->nullable();
                $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('snoozed_until')->nullable();
                $table->timestamp('detected_at')->index();
                $table->timestamp('last_outreach_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_note')->nullable();
                $table->timestamps();

                $table->index(['situation_key', 'status'], 'staff_outreach_situ_status_idx');
                $table->index('conversation_thread_review_id', 'staff_outreach_thread_review_idx');
                $table->index('snoozed_until', 'staff_outreach_snoozed_idx');
            });
        }

        if (! Schema::hasTable('staff_proactive_outreach_logs')) {
            Schema::create('staff_proactive_outreach_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('outreach_item_id')->constrained('staff_proactive_outreach_items')->cascadeOnDelete();
                $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('template_id')->nullable()->constrained('staff_response_templates')->nullOnDelete();
                $table->string('channel', 24)->default('both');
                $table->string('subject');
                $table->text('body');
                $table->timestamp('sent_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_proactive_outreach_logs');
        Schema::dropIfExists('staff_proactive_outreach_items');
        Schema::dropIfExists('staff_response_templates');
    }
};
