<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            if (! Schema::hasColumn('reviews', 'authenticity_flag')) {
                $table->string('authenticity_flag', 24)->default('clean')->index()->after('status');
            }
            if (! Schema::hasColumn('reviews', 'quality_score')) {
                $table->unsignedTinyInteger('quality_score')->nullable()->after('authenticity_flag');
            }
            if (! Schema::hasColumn('reviews', 'is_brief')) {
                $table->boolean('is_brief')->default(false)->after('quality_score');
            }
            if (! Schema::hasColumn('reviews', 'sentiment_score')) {
                $table->decimal('sentiment_score', 4, 3)->nullable()->after('is_brief');
            }
            if (! Schema::hasColumn('reviews', 'reviewer_subnet')) {
                $table->string('reviewer_subnet', 64)->nullable()->index()->after('sentiment_score');
            }
            if (! Schema::hasColumn('reviews', 'moderation_cluster_id')) {
                $table->foreignId('moderation_cluster_id')->nullable()->after('reviewer_subnet');
            }
        });

        if (! Schema::hasTable('review_moderation_clusters')) {
            Schema::create('review_moderation_clusters', function (Blueprint $table): void {
                $table->id();
                $table->string('cluster_type', 32)->index();
                $table->foreignId('primary_reviewee_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->string('status', 24)->default('open')->index();
                $table->timestamps();
            });

            Schema::table('reviews', function (Blueprint $table): void {
                if (Schema::hasColumn('reviews', 'moderation_cluster_id')) {
                    $table->foreign('moderation_cluster_id')
                        ->references('id')
                        ->on('review_moderation_clusters')
                        ->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('review_authenticity_signals')) {
            Schema::create('review_authenticity_signals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('review_id')->constrained()->cascadeOnDelete();
                $table->string('signal_type', 48)->index();
                $table->string('label')->nullable();
                $table->json('metadata')->nullable();
                $table->decimal('confidence', 4, 3)->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('review_amendment_requests')) {
            Schema::create('review_amendment_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('review_id')->constrained()->cascadeOnDelete();
                $table->foreignId('issued_by')->constrained('users')->cascadeOnDelete();
                $table->text('instructions');
                $table->json('required_changes')->nullable();
                $table->timestamp('expires_at')->index();
                $table->string('status', 24)->default('open')->index();
                $table->timestamp('responded_at')->nullable();
                $table->string('default_action', 24)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('review_moderation_action_logs')) {
            Schema::create('review_moderation_action_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('review_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 64)->index();
                $table->text('note')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('occurred_at')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('review_manipulation_reports')) {
            Schema::create('review_manipulation_reports', function (Blueprint $table): void {
                $table->id();
                $table->string('report_type', 32)->index();
                $table->date('report_date')->index();
                $table->json('payload');
                $table->timestamp('generated_at');
                $table->timestamps();

                $table->unique(['report_type', 'report_date'], 'review_manip_report_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('review_manipulation_reports');
        Schema::dropIfExists('review_moderation_action_logs');
        Schema::dropIfExists('review_amendment_requests');
        Schema::dropIfExists('review_authenticity_signals');

        if (Schema::hasTable('reviews') && Schema::hasColumn('reviews', 'moderation_cluster_id')) {
            Schema::table('reviews', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('moderation_cluster_id');
            });
        }

        Schema::dropIfExists('review_moderation_clusters');

        Schema::table('reviews', function (Blueprint $table): void {
            foreach (['authenticity_flag', 'quality_score', 'is_brief', 'sentiment_score', 'reviewer_subnet'] as $col) {
                if (Schema::hasColumn('reviews', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
