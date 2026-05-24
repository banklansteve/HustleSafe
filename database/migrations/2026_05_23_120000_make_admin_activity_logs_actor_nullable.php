<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_activity_logs')) {
            return;
        }

        Schema::table('admin_activity_logs', function (Blueprint $table): void {
            $table->dropForeign(['actor_user_id']);
        });

        Schema::table('admin_activity_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('actor_user_id')->nullable()->change();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('action');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_activity_logs')) {
            return;
        }

        Schema::table('admin_activity_logs', function (Blueprint $table): void {
            $table->dropForeign(['actor_user_id']);
            $table->dropIndex(['action']);
        });

        Schema::table('admin_activity_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('actor_user_id')->nullable(false)->change();
            $table->foreign('actor_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
