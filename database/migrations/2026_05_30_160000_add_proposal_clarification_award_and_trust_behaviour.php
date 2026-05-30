<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_clarification_threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quest_offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('open');
            $table->unsignedTinyInteger('questions_asked')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique('quest_offer_id');
            $table->index(['quest_id', 'status']);
        });

        Schema::create('proposal_clarification_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('thread_id')->constrained('proposal_clarification_threads')->cascadeOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 16);
            $table->string('prompt_key', 64)->nullable();
            $table->string('prompt_category', 32)->nullable();
            $table->text('body');
            $table->timestamps();

            $table->index(['thread_id', 'created_at']);
        });

        Schema::create('proposal_behaviour_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quest_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['user_id', 'event_type']);
            $table->index(['quest_id', 'event_type']);
        });

        Schema::table('quest_offers', function (Blueprint $table): void {
            $table->timestamp('award_client_confirmed_at')->nullable()->after('shortlisted_at');
            $table->timestamp('award_freelancer_confirmed_at')->nullable()->after('award_client_confirmed_at');
            $table->json('award_terms_snapshot')->nullable()->after('award_freelancer_confirmed_at');
        });

        Schema::table('quests', function (Blueprint $table): void {
            $table->unsignedBigInteger('pending_award_offer_id')->nullable()->after('accepted_quest_offer_id');
            $table->foreign('pending_award_offer_id', 'quests_pending_award_offer_fk')
                ->references('id')->on('quest_offers')->nullOnDelete();
        });

        Schema::table('user_trust_metrics', function (Blueprint $table): void {
            $table->unsignedSmallInteger('reliability_penalty_points')->default(0)->after('client_trust_score');
            $table->unsignedSmallInteger('shortlisted_withdrawal_count')->default(0)->after('reliability_penalty_points');
            $table->unsignedSmallInteger('client_proposal_ghost_strikes')->default(0)->after('shortlisted_withdrawal_count');
            $table->boolean('client_quest_posting_flagged')->default(false)->after('client_proposal_ghost_strikes');
        });
    }

    public function down(): void
    {
        Schema::table('user_trust_metrics', function (Blueprint $table): void {
            $table->dropColumn([
                'reliability_penalty_points',
                'shortlisted_withdrawal_count',
                'client_proposal_ghost_strikes',
                'client_quest_posting_flagged',
            ]);
        });

        Schema::table('quests', function (Blueprint $table): void {
            $table->dropForeign('quests_pending_award_offer_fk');
            $table->dropColumn('pending_award_offer_id');
        });

        Schema::table('quest_offers', function (Blueprint $table): void {
            $table->dropColumn([
                'award_client_confirmed_at',
                'award_freelancer_confirmed_at',
                'award_terms_snapshot',
            ]);
        });

        Schema::dropIfExists('proposal_behaviour_logs');
        Schema::dropIfExists('proposal_clarification_messages');
        Schema::dropIfExists('proposal_clarification_threads');
    }
};
