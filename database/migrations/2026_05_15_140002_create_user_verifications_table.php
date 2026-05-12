<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            /** identity | address | qualification */
            $table->string('category', 32)->index();
            $table->foreignId('freelancer_credential_id')->nullable()->constrained('freelancer_credentials')->nullOnDelete();
            /** pending | in_review | approved | rejected | expired */
            $table->string('status', 24)->default('pending')->index();
            $table->json('document_paths')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_verifications');
    }
};
