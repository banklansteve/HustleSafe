<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversation_policy_warnings') && ! Schema::hasColumn('conversation_policy_warnings', 'acknowledged_at')) {
            Schema::table('conversation_policy_warnings', function (Blueprint $table): void {
                $table->timestamp('acknowledged_at')->nullable()->after('note');
            });
        }

        if (Schema::hasTable('admin_user_sanctions') && ! Schema::hasColumn('admin_user_sanctions', 'user_acknowledged_at')) {
            Schema::table('admin_user_sanctions', function (Blueprint $table): void {
                $table->timestamp('user_acknowledged_at')->nullable()->after('reversal_reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('conversation_policy_warnings', 'acknowledged_at')) {
            Schema::table('conversation_policy_warnings', function (Blueprint $table): void {
                $table->dropColumn('acknowledged_at');
            });
        }

        if (Schema::hasColumn('admin_user_sanctions', 'user_acknowledged_at')) {
            Schema::table('admin_user_sanctions', function (Blueprint $table): void {
                $table->dropColumn('user_acknowledged_at');
            });
        }
    }
};
