<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_monitoring_terms')) {
            Schema::create('conversation_monitoring_terms', function (Blueprint $table): void {
                $table->id();
                $table->string('term_type', 32)->index();
                $table->string('pattern');
                $table->boolean('is_wildcard')->default(false);
                $table->boolean('is_active')->default(true)->index();
                $table->string('locale_hint', 24)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('conversation_message_flags')) {
            Schema::create('conversation_message_flags', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quest_conversation_thread_id')->constrained()->cascadeOnDelete();
                $table->foreignId('quest_conversation_message_id')->constrained()->cascadeOnDelete();
                $table->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('quest_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('quest_offer_id')->nullable()->constrained()->nullOnDelete();
                $table->string('trigger_category', 48)->index();
                $table->string('matched_pattern_redacted', 500);
                $table->decimal('confidence', 4, 3)->default(1);
                $table->string('status', 24)->default('pending')->index();
                $table->timestamp('flagged_at')->index();
                $table->timestamps();

                $table->index(['quest_conversation_thread_id', 'status'], 'conv_msg_flags_thread_status_idx');
                $table->index(['sender_user_id', 'flagged_at'], 'conv_msg_flags_sender_date_idx');
            });
        }

        if (! Schema::hasTable('conversation_thread_reviews')) {
            Schema::create('conversation_thread_reviews', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quest_conversation_thread_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
                $table->string('status', 32)->default('pending')->index();
                $table->string('priority', 16)->default('normal')->index();
                $table->json('trigger_categories')->nullable();
                $table->unsignedSmallInteger('flag_count')->default(0);
                $table->timestamp('first_flagged_at')->nullable();
                $table->timestamp('last_flagged_at')->nullable();
                $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('escalated_to_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('escalated_at')->nullable();
                $table->string('dismiss_reason', 500)->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('conversation_policy_warnings')) {
            Schema::create('conversation_policy_warnings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('thread_review_id')->nullable()->constrained('conversation_thread_reviews')->nullOnDelete();
                $table->foreignId('issued_by')->constrained('users')->cascadeOnDelete();
                $table->text('note');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('conversation_user_health_scores')) {
            Schema::create('conversation_user_health_scores', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('health_score')->default(100);
                $table->unsignedSmallInteger('flag_count_30d')->default(0);
                $table->unsignedSmallInteger('distinct_counterparties_30d')->default(0);
                $table->timestamp('calculated_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('conversation_systematic_escalations')) {
            Schema::create('conversation_systematic_escalations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('trigger_category', 48)->index();
                $table->string('status', 24)->default('open')->index();
                $table->unsignedSmallInteger('instance_count')->default(0);
                $table->unsignedSmallInteger('distinct_counterparties')->default(0);
                $table->unsignedSmallInteger('distinct_contracts')->default(0);
                $table->json('timeline')->nullable();
                $table->text('resolution_note')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('detected_at')->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('conversation_monitoring_terms')) {
            $defaults = [
                ['term_type' => 'abusive_blacklist', 'pattern' => 'scam'],
                ['term_type' => 'abusive_blacklist', 'pattern' => 'fraudster'],
                ['term_type' => 'abusive_blacklist', 'pattern' => 'threat'],
                ['term_type' => 'custom_keyword', 'pattern' => 'wire transfer'],
            ];
            foreach ($defaults as $row) {
                if (! \Illuminate\Support\Facades\DB::table('conversation_monitoring_terms')->where('pattern', $row['pattern'])->exists()) {
                    \Illuminate\Support\Facades\DB::table('conversation_monitoring_terms')->insert([
                        ...$row,
                        'is_wildcard' => false,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_systematic_escalations');
        Schema::dropIfExists('conversation_user_health_scores');
        Schema::dropIfExists('conversation_policy_warnings');
        Schema::dropIfExists('conversation_thread_reviews');
        Schema::dropIfExists('conversation_message_flags');
        Schema::dropIfExists('conversation_monitoring_terms');
    }
};
