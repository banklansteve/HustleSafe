<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_chat_assignments')) {
            Schema::create('support_chat_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quest_conversation_thread_id')->nullable()->constrained('quest_conversation_threads')->nullOnDelete();
                $table->foreignId('assigned_admin_id')->constrained('users')->cascadeOnDelete();
                $table->string('status', 24)->default('open')->index();
                $table->timestamp('assigned_at')->useCurrent();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('quest_conversation_thread_id')->nullable()->constrained('quest_conversation_threads')->nullOnDelete();
                $table->foreignId('opened_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('subject');
                $table->string('category', 80)->default('general')->index();
                $table->string('priority', 24)->default('medium')->index();
                $table->string('status', 32)->default('open')->index();
                $table->text('description')->nullable();
                $table->text('resolution_summary')->nullable();
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_ticket_messages')) {
            Schema::create('support_ticket_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('sender_type', 24)->default('admin')->index();
                $table->string('visibility', 24)->default('public')->index();
                $table->text('body');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff_bulk_message_requests')) {
            Schema::create('staff_bulk_message_requests', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('created_by_admin_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('approved_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 32)->default('pending_authorisation')->index();
                $table->string('audience', 80)->default('all_users')->index();
                $table->json('channels')->nullable();
                $table->string('subject');
                $table->text('body');
                $table->unsignedInteger('recipients_count')->default(0);
                $table->text('approval_note')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('dispatched_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_bulk_message_requests');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('support_chat_assignments');
    }
};
