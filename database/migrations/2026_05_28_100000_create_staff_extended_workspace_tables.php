<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_sanction_appeals')) {
            Schema::create('staff_sanction_appeals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('admin_user_sanction_id')->constrained('admin_user_sanctions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('statement');
                $table->json('evidence')->nullable();
                $table->string('status', 32)->default('pending')->index();
                $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewed_by_staff_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('escalated_to_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('decision_note')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff_review_integrity_cases')) {
            Schema::create('staff_review_integrity_cases', function (Blueprint $table): void {
                $table->id();
                $table->string('pattern_type', 64)->index();
                $table->string('pattern_key')->index();
                $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('pattern_data')->nullable();
                $table->string('status', 32)->default('open')->index();
                $table->foreignId('investigated_by_staff_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('findings')->nullable();
                $table->json('flagged_review_ids')->nullable();
                $table->boolean('escalated_to_super_admin')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff_escrow_anomaly_notes')) {
            Schema::create('staff_escrow_anomaly_notes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quest_id')->constrained('quests')->cascadeOnDelete();
                $table->string('anomaly_type', 64)->index();
                $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('status', 32)->default('open')->index();
                $table->text('outreach_summary')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff_badge_requests')) {
            Schema::create('staff_badge_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('badge_slug', 80)->index();
                $table->string('status', 32)->default('pending')->index();
                $table->text('applicant_note')->nullable();
                $table->json('metrics_snapshot')->nullable();
                $table->foreignId('reviewed_by_staff_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('decision_note')->nullable();
                $table->boolean('escalated_to_super_admin')->default(false);
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff_knowledge_articles')) {
            Schema::create('staff_knowledge_articles', function (Blueprint $table): void {
                $table->id();
                $table->string('slug')->unique();
                $table->string('title');
                $table->string('category', 80)->index();
                $table->longText('body');
                $table->string('status', 24)->default('published')->index();
                $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff_knowledge_suggestions')) {
            Schema::create('staff_knowledge_suggestions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('staff_knowledge_article_id')->nullable()->constrained('staff_knowledge_articles')->nullOnDelete();
                $table->foreignId('suggested_by_staff_id')->constrained('users')->cascadeOnDelete();
                $table->text('body');
                $table->string('status', 24)->default('pending')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff_team_chat_rooms')) {
            Schema::create('staff_team_chat_rooms', function (Blueprint $table): void {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->string('type', 32)->default('global')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff_team_chat_messages')) {
            Schema::create('staff_team_chat_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('staff_team_chat_room_id')->constrained('staff_team_chat_rooms')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('body')->nullable();
                $table->json('attachments')->nullable();
                $table->json('mentions')->nullable();
                $table->boolean('is_official_guidance')->default(false);
                $table->foreignId('removed_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('removed_at')->nullable();
                $table->timestamps();
                $table->index(['staff_team_chat_room_id', 'created_at'], 'staff_chat_room_created_idx');
            });
        }

        if (! Schema::hasTable('staff_team_chat_reactions')) {
            Schema::create('staff_team_chat_reactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('staff_team_chat_message_id')->constrained('staff_team_chat_messages')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('emoji', 16);
                $table->timestamps();
                $table->unique(['staff_team_chat_message_id', 'user_id', 'emoji'], 'staff_chat_reaction_unique');
            });
        }

        if (! Schema::hasTable('staff_team_chat_pins')) {
            Schema::create('staff_team_chat_pins', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('staff_team_chat_room_id')->constrained('staff_team_chat_rooms')->cascadeOnDelete();
                $table->foreignId('staff_team_chat_message_id')->constrained('staff_team_chat_messages')->cascadeOnDelete();
                $table->foreignId('pinned_by_admin_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff_team_chat_reads')) {
            Schema::create('staff_team_chat_reads', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('staff_team_chat_message_id')->constrained('staff_team_chat_messages')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('read_at');
                $table->unique(['staff_team_chat_message_id', 'user_id'], 'staff_chat_read_unique');
            });
        }

        if (Schema::hasTable('staff_team_chat_rooms') && DB::table('staff_team_chat_rooms')->where('slug', 'global')->doesntExist()) {
            DB::table('staff_team_chat_rooms')->insert([
                'slug' => 'global',
                'name' => 'Operations team',
                'type' => 'global',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_team_chat_reads');
        Schema::dropIfExists('staff_team_chat_pins');
        Schema::dropIfExists('staff_team_chat_reactions');
        Schema::dropIfExists('staff_team_chat_messages');
        Schema::dropIfExists('staff_team_chat_rooms');
        Schema::dropIfExists('staff_knowledge_suggestions');
        Schema::dropIfExists('staff_knowledge_articles');
        Schema::dropIfExists('staff_badge_requests');
        Schema::dropIfExists('staff_escrow_anomaly_notes');
        Schema::dropIfExists('staff_review_integrity_cases');
        Schema::dropIfExists('staff_sanction_appeals');
    }
};
