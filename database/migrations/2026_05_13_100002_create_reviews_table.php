<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quest_id')->constrained('quests')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewee_id')->constrained('users')->cascadeOnDelete();
            /** Party role at review time — client or freelancer */
            $table->string('reviewer_party', 20);
            /** full = star rating allowed; partial = feedback without rating */
            $table->string('review_type', 20)->index();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            $table->json('tags')->nullable();
            /** draft | published | locked */
            $table->string('status', 20)->default('published')->index();
            $table->timestamp('edit_window_ends_at');
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['quest_id', 'reviewer_id']);
            $table->index(['reviewee_id', 'review_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
