<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quest_offers', function (Blueprint $table) {
            if (! Schema::hasColumn('quest_offers', 'scope_detail')) {
                $table->longText('scope_detail')->nullable()->after('pitch');
            }
            if (! Schema::hasColumn('quest_offers', 'warranty_terms')) {
                $table->text('warranty_terms')->nullable();
            }
            if (! Schema::hasColumn('quest_offers', 'proposed_completion_date')) {
                $table->date('proposed_completion_date')->nullable();
            }
            if (! Schema::hasColumn('quest_offers', 'planned_start_date')) {
                $table->date('planned_start_date')->nullable();
            }
            if (! Schema::hasColumn('quest_offers', 'planned_finish_date')) {
                $table->date('planned_finish_date')->nullable();
            }
            if (! Schema::hasColumn('quest_offers', 'materials')) {
                $table->json('materials')->nullable();
            }
            if (! Schema::hasColumn('quest_offers', 'pricing_snapshot')) {
                $table->json('pricing_snapshot')->nullable();
            }
        });

        if (! Schema::hasTable('quest_conversation_threads')) {
            Schema::create('quest_conversation_threads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
                $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedInteger('messages_count')->default(0);
                $table->timestamp('last_message_at')->nullable();
                $table->timestamp('freelancer_last_read_at')->nullable();
                $table->timestamp('client_last_read_at')->nullable();
                $table->timestamps();

                $table->unique(['quest_id', 'freelancer_id']);
            });
        }

        if (! Schema::hasTable('quest_conversation_messages')) {
            Schema::create('quest_conversation_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quest_conversation_thread_id')->constrained('quest_conversation_threads')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('body');
                $table->timestamps();

                $table->index(['quest_conversation_thread_id', 'created_at'], 'qcm_thread_created_idx');
            });
        }

        if (! Schema::hasTable('content_reports')) {
            Schema::create('content_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('reportable_type');
                $table->unsignedBigInteger('reportable_id');
                $table->index(['reportable_type', 'reportable_id'], 'content_reports_reportable_idx');
                $table->string('reason', 64);
                $table->text('details')->nullable();
                $table->string('status', 24)->default('open')->index();
                $table->timestamps();

                $table->index(['status', 'created_at'], 'content_reports_status_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reports');
        Schema::dropIfExists('quest_conversation_messages');
        Schema::dropIfExists('quest_conversation_threads');

        Schema::table('quest_offers', function (Blueprint $table) {
            $table->dropColumn([
                'scope_detail',
                'warranty_terms',
                'proposed_completion_date',
                'planned_start_date',
                'planned_finish_date',
                'materials',
                'pricing_snapshot',
            ]);
        });
    }
};
