<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quest_disputes', function (Blueprint $table): void {
            $table->string('outcome_action', 48)->nullable()->after('resolution_outcome');
            $table->timestamp('extended_deadline_at')->nullable()->after('outcome_action');
            $table->timestamp('chargeback_risk_flagged_at')->nullable()->after('extended_deadline_at');
            $table->timestamp('pattern_investigation_at')->nullable()->after('chargeback_risk_flagged_at');
            $table->string('report_path')->nullable()->after('pattern_investigation_at');
            $table->timestamp('report_generated_at')->nullable()->after('report_path');
            $table->timestamp('sealed_at')->nullable()->after('finalized_at');
        });

        Schema::create('dispute_mediation_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quest_dispute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 24)->default('scheduled');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('meeting_url', 500)->nullable();
            $table->text('instructions')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['quest_dispute_id', 'status']);
        });

        Schema::create('dispute_precedents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quest_dispute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('summary');
            $table->string('category', 64)->nullable();
            $table->json('linked_dispute_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_precedents');
        Schema::dropIfExists('dispute_mediation_sessions');

        Schema::table('quest_disputes', function (Blueprint $table): void {
            $table->dropColumn([
                'outcome_action',
                'extended_deadline_at',
                'chargeback_risk_flagged_at',
                'pattern_investigation_at',
                'report_path',
                'report_generated_at',
                'sealed_at',
            ]);
        });
    }
};
