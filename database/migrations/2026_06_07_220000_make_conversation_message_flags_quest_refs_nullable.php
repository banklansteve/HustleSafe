<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_message_flags')) {
            return;
        }

        Schema::table('conversation_message_flags', function (Blueprint $table): void {
            if ($this->foreignKeyExists('conversation_message_flags', 'conversation_message_flags_quest_conversation_thread_id_foreign')) {
                $table->dropForeign('conversation_message_flags_quest_conversation_thread_id_foreign');
            } elseif ($this->foreignKeyExists('conversation_message_flags', 'conv_msg_flags_quest_thread_fk')) {
                $table->dropForeign('conv_msg_flags_quest_thread_fk');
            } else {
                $table->dropForeign(['quest_conversation_thread_id']);
            }

            if ($this->foreignKeyExists('conversation_message_flags', 'conversation_message_flags_quest_conversation_message_id_foreign')) {
                $table->dropForeign('conversation_message_flags_quest_conversation_message_id_foreign');
            } elseif ($this->foreignKeyExists('conversation_message_flags', 'conv_msg_flags_quest_message_fk')) {
                $table->dropForeign('conv_msg_flags_quest_message_fk');
            } else {
                $table->dropForeign(['quest_conversation_message_id']);
            }
        });

        Schema::table('conversation_message_flags', function (Blueprint $table): void {
            $table->unsignedBigInteger('quest_conversation_thread_id')->nullable()->change();
            $table->unsignedBigInteger('quest_conversation_message_id')->nullable()->change();
        });

        Schema::table('conversation_message_flags', function (Blueprint $table): void {
            if (! $this->foreignKeyExists('conversation_message_flags', 'conv_msg_flags_quest_thread_fk')) {
                $table->foreign('quest_conversation_thread_id', 'conv_msg_flags_quest_thread_fk')
                    ->references('id')
                    ->on('quest_conversation_threads')
                    ->cascadeOnDelete();
            }

            if (! $this->foreignKeyExists('conversation_message_flags', 'conv_msg_flags_quest_message_fk')) {
                $table->foreign('quest_conversation_message_id', 'conv_msg_flags_quest_message_fk')
                    ->references('id')
                    ->on('quest_conversation_messages')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('conversation_message_flags')) {
            return;
        }

        Schema::table('conversation_message_flags', function (Blueprint $table): void {
            if ($this->foreignKeyExists('conversation_message_flags', 'conv_msg_flags_quest_thread_fk')) {
                $table->dropForeign('conv_msg_flags_quest_thread_fk');
            }

            if ($this->foreignKeyExists('conversation_message_flags', 'conv_msg_flags_quest_message_fk')) {
                $table->dropForeign('conv_msg_flags_quest_message_fk');
            }
        });

        Schema::table('conversation_message_flags', function (Blueprint $table): void {
            $table->unsignedBigInteger('quest_conversation_thread_id')->nullable(false)->change();
            $table->unsignedBigInteger('quest_conversation_message_id')->nullable(false)->change();
        });

        Schema::table('conversation_message_flags', function (Blueprint $table): void {
            $table->foreign('quest_conversation_thread_id')
                ->references('id')
                ->on('quest_conversation_threads')
                ->cascadeOnDelete();

            $table->foreign('quest_conversation_message_id')
                ->references('id')
                ->on('quest_conversation_messages')
                ->cascadeOnDelete();
        });
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ? LIMIT 1',
            [$database, $table, $name, 'FOREIGN KEY'],
        );

        return $result !== [];
    }
};
