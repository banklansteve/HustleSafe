<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 40)->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('action_label')->nullable();
            $table->string('action_url')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamp('snoozed_until')->nullable()->index();
            $table->timestamp('actioned_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('admin_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by_admin_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type', 80)->nullable()->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('medium')->index();
            $table->string('status', 24)->default('todo')->index();
            $table->date('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_fraud_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('case_number', 40)->unique();
            $table->string('risk_type', 80)->index();
            $table->unsignedTinyInteger('risk_score')->default(0)->index();
            $table->string('status', 24)->default('open')->index();
            $table->text('summary');
            $table->json('signals')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_risk_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('category', 60)->index();
            $table->unsignedTinyInteger('severity')->default(50);
            $table->boolean('is_active')->default(true)->index();
            $table->json('conditions')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_compliance_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_type', 50)->index();
            $table->string('status', 24)->default('open')->index();
            $table->string('reference', 40)->unique();
            $table->text('requester_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_compliance_requests');
        Schema::dropIfExists('admin_risk_rules');
        Schema::dropIfExists('admin_fraud_cases');
        Schema::dropIfExists('admin_tasks');
        Schema::dropIfExists('admin_notifications');
    }
};
