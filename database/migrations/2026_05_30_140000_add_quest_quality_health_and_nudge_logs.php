<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table): void {
            $table->json('quality_gate_feedback')->nullable()->after('description');
            $table->timestamp('quality_gate_failed_at')->nullable()->after('quality_gate_feedback');
            $table->unsignedTinyInteger('health_score')->nullable()->after('offers_count');
            $table->timestamp('health_score_updated_at')->nullable()->after('health_score');
        });

        Schema::create('quest_nudge_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
            $table->string('nudge_type', 64);
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 32)->default('mail');
            $table->string('subject');
            $table->text('body')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['quest_id', 'nudge_type', 'recipient_user_id'], 'quest_nudge_unique_per_recipient');
            $table->index(['quest_id', 'sent_at']);
            $table->index('nudge_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_nudge_logs');

        Schema::table('quests', function (Blueprint $table): void {
            $table->dropColumn([
                'quality_gate_feedback',
                'quality_gate_failed_at',
                'health_score',
                'health_score_updated_at',
            ]);
        });
    }
};
