<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quest_patrol_flags', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->string('flag_type', 64);
            $table->string('severity', 16)->default('medium');
            $table->string('status', 24)->default('open');
            $table->string('fingerprint', 160)->unique();
            $table->json('meta')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('dismissed_at')->nullable();
            $table->foreignId('dismissed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dismissal_reason_code', 64)->nullable();
            $table->text('dismissal_reason')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'status'], 'qpf_subject_status_idx');
            $table->index(['flag_type', 'status'], 'qpf_type_status_idx');
            $table->index(['detected_at'], 'qpf_detected_idx');
        });

        Schema::create('quest_patrol_actions', function (Blueprint $table) {
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

            $table->index(['subject_type', 'subject_id', 'occurred_at'], 'qpa_subject_occurred_idx');
        });

        if (Schema::hasTable('quest_offers')) {
            Schema::table('quest_offers', function (Blueprint $table) {
                if (! Schema::hasColumn('quest_offers', 'admin_quality_rating')) {
                    $table->unsignedTinyInteger('admin_quality_rating')->nullable()->after('admin_status_reason');
                }
                if (! Schema::hasColumn('quest_offers', 'admin_recommended_at')) {
                    $table->timestamp('admin_recommended_at')->nullable()->after('admin_quality_rating');
                }
                if (! Schema::hasColumn('quest_offers', 'admin_recommended_by_id')) {
                    $table->foreignId('admin_recommended_by_id')->nullable()->after('admin_recommended_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('quest_offers', 'admin_hidden_from_client')) {
                    $table->boolean('admin_hidden_from_client')->default(false)->after('admin_recommended_by_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('quest_offers')) {
            Schema::table('quest_offers', function (Blueprint $table) {
                foreach (['admin_hidden_from_client', 'admin_recommended_by_id', 'admin_recommended_at', 'admin_quality_rating'] as $column) {
                    if (Schema::hasColumn('quest_offers', $column)) {
                        if ($column === 'admin_recommended_by_id') {
                            $table->dropConstrainedForeignId($column);
                        } else {
                            $table->dropColumn($column);
                        }
                    }
                }
            });
        }

        Schema::dropIfExists('quest_patrol_actions');
        Schema::dropIfExists('quest_patrol_flags');
    }
};
