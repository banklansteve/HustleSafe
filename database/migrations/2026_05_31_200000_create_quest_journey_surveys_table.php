<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quest_journey_surveys', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('quest_id')->constrained('quests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('quest_offer_id')->nullable()->constrained('quest_offers')->nullOnDelete();
            $table->string('cohort', 48);
            $table->string('rejection_reason', 48)->nullable();
            $table->json('answers')->nullable();
            $table->string('first_question_key', 64)->nullable();
            $table->string('first_answer_value', 64)->nullable();
            $table->timestamp('first_answer_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('email_send_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->boolean('operational_flagged')->default(false);
            $table->timestamps();

            $table->unique(['quest_id', 'user_id', 'cohort']);
            $table->index(['cohort', 'submitted_at']);
            $table->index(['expires_at', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_journey_surveys');
    }
};
