<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('onboarding_quality_review_actions')) {
            return;
        }

        Schema::create('onboarding_quality_review_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('onboarding_quality_review_id');
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 60)->index();
            $table->text('notes')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['onboarding_quality_review_id', 'created_at'], 'ob_qc_review_actions_review_created_idx');

            $table->foreign('onboarding_quality_review_id', 'ob_qc_actions_review_fk')
                ->references('id')
                ->on('onboarding_quality_reviews')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_quality_review_actions');
    }
};
