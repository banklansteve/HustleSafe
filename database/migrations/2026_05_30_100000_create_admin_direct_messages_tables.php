<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_direct_conversations')) {
            Schema::create('admin_direct_conversations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_one_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('user_two_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('last_message_id')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();

                $table->unique(['user_one_id', 'user_two_id'], 'admin_dm_pair_unique');
                $table->index('last_message_at');
            });
        }

        if (! Schema::hasTable('admin_direct_messages')) {
            Schema::create('admin_direct_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('admin_direct_conversation_id')->constrained('admin_direct_conversations')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('body')->nullable();
                $table->json('attachments')->nullable();
                $table->json('mentions')->nullable();
                $table->timestamps();

                $table->index(['admin_direct_conversation_id', 'created_at'], 'admin_dm_msg_conv_created_idx');
            });
        }

        if (! Schema::hasTable('admin_direct_message_receipts')) {
            Schema::create('admin_direct_message_receipts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('admin_direct_message_id')->constrained('admin_direct_messages')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->unique(['admin_direct_message_id', 'user_id'], 'admin_dm_receipt_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_direct_message_receipts');
        Schema::dropIfExists('admin_direct_messages');
        Schema::dropIfExists('admin_direct_conversations');
    }
};
