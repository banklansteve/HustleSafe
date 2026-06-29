<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('quest_disputes', 'management_status')) {
            Schema::table('quest_disputes', function (Blueprint $table): void {
                $table->string('management_status', 32)->default('open')->index()->after('status');
                $table->string('severity', 16)->default('medium')->index()->after('management_status');
                $table->unsignedTinyInteger('reassignment_count')->default(0)->after('assigned_staff_id');
                $table->timestamp('ready_for_decision_at')->nullable()->after('staff_claimed_at');
                $table->timestamp('management_resolved_at')->nullable()->after('resolved_at');
                $table->timestamp('appeal_window_ends_at')->nullable()->after('management_resolved_at');
                $table->timestamp('finalized_at')->nullable()->after('appeal_window_ends_at');
                $table->foreignId('super_admin_decided_by')->nullable()->after('ruling_favoured_user_id')->constrained('users')->nullOnDelete();
                $table->timestamp('super_admin_decided_at')->nullable()->after('super_admin_decided_by');
                $table->text('super_admin_decision_notes')->nullable()->after('super_admin_decided_at');
                $table->json('sanction_payload')->nullable()->after('super_admin_decision_notes');
            });
        }

        if (! Schema::hasTable('dispute_assessments')) {
            Schema::create('dispute_assessments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quest_dispute_id')->constrained('quest_disputes')->cascadeOnDelete();
                $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedTinyInteger('quality_rating')->nullable();
                $table->json('investigation_checklist')->nullable();
                $table->string('violation_status', 48)->nullable();
                $table->json('key_findings')->nullable();
                $table->string('recommendation', 48)->nullable();
                $table->unsignedTinyInteger('recommended_client_share_percent')->nullable();
                $table->text('reasoning')->nullable();
                $table->unsignedSmallInteger('time_spent_minutes')->nullable();
                $table->string('status', 24)->default('draft')->index();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();

                $table->index(['quest_dispute_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_assessments');

        if (Schema::hasColumn('quest_disputes', 'management_status')) {
            Schema::table('quest_disputes', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('super_admin_decided_by');
                $table->dropColumn([
                    'management_status',
                    'severity',
                    'reassignment_count',
                    'ready_for_decision_at',
                    'management_resolved_at',
                    'appeal_window_ends_at',
                    'finalized_at',
                    'super_admin_decided_at',
                    'super_admin_decision_notes',
                    'sanction_payload',
                ]);
            });
        }
    }
};
