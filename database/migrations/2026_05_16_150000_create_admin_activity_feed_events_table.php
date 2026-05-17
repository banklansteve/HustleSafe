<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_activity_feed_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('category', 32)->index();
            $table->string('event_key', 80)->index();
            $table->string('severity', 24)->default('info')->index();
            $table->string('title');
            $table->text('summary');
            $table->json('entities')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('amount_minor')->nullable()->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type')->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->foreignId('local_government_id')->nullable()->constrained('local_governments')->nullOnDelete();
            $table->foreignId('quest_category_id')->nullable()->constrained('quest_categories')->nullOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['category', 'occurred_at']);
            $table->index(['severity', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_feed_events');
    }
};
