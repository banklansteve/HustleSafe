<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_type', 64);
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('requested_by_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->string('status', 24)->default('pending');
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'mar_status_created_idx');
            $table->index(['subject_type', 'subject_id'], 'mar_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_approval_requests');
    }
};
