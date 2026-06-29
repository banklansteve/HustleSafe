<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quest_disputes', function (Blueprint $table): void {
            $table->timestamp('staff_acknowledged_at')->nullable()->after('staff_claimed_at');
            $table->json('workflow_state')->nullable()->after('staff_acknowledged_at');
            $table->timestamp('held_at')->nullable()->after('ready_for_decision_at');
            $table->text('hold_reason')->nullable()->after('held_at');
        });

        Schema::table('dispute_assessments', function (Blueprint $table): void {
            $table->unsignedTinyInteger('super_admin_rating')->nullable()->after('time_spent_minutes');
            $table->text('super_admin_feedback')->nullable()->after('super_admin_rating');
            $table->string('recommended_sanction', 48)->nullable()->after('super_admin_feedback');
        });
    }

    public function down(): void
    {
        Schema::table('quest_disputes', function (Blueprint $table): void {
            $table->dropColumn(['staff_acknowledged_at', 'workflow_state', 'held_at', 'hold_reason']);
        });

        Schema::table('dispute_assessments', function (Blueprint $table): void {
            $table->dropColumn(['super_admin_rating', 'super_admin_feedback', 'recommended_sanction']);
        });
    }
};
