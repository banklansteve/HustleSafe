<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_policy_warning_replies')) {
            return;
        }

        Schema::table('conversation_policy_warning_replies', function (Blueprint $table): void {
            if (! Schema::hasColumn('conversation_policy_warning_replies', 'conversation_policy_warning_id')) {
                return;
            }
        });

        $connection = Schema::getConnection();
        $foreignKeys = collect($connection->select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'conversation_policy_warning_replies'
             AND COLUMN_NAME = 'conversation_policy_warning_id'
             AND REFERENCED_TABLE_NAME IS NOT NULL"
        ))->pluck('CONSTRAINT_NAME');

        if ($foreignKeys->isEmpty()) {
            Schema::table('conversation_policy_warning_replies', function (Blueprint $table): void {
                $table->foreign('conversation_policy_warning_id', 'policy_warning_replies_warning_fk')
                    ->references('id')
                    ->on('conversation_policy_warnings')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('conversation_policy_warning_replies')) {
            return;
        }

        Schema::table('conversation_policy_warning_replies', function (Blueprint $table): void {
            $table->dropForeign('policy_warning_replies_warning_fk');
        });
    }
};
