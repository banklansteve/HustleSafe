<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_sla_clocks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('sla_key');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('triggered_at');
            $table->timestamp('due_at');
            $table->timestamp('breached_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['sla_key', 'status']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['due_at', 'status']);
            $table->index(['assigned_admin_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_sla_clocks');
    }
};
