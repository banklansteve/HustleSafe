<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds foreign keys to admin_activity_feed_events when the table was created
 * without them due to an earlier partial migration failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_activity_feed_events')) {
            return;
        }

        Schema::table('admin_activity_feed_events', function (Blueprint $table): void {
            if (Schema::hasTable('users') && ! $this->hasForeign('admin_activity_feed_events', 'actor_user_id')) {
                $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            }
            if (Schema::hasTable('states') && ! $this->hasForeign('admin_activity_feed_events', 'state_id')) {
                $table->foreign('state_id')->references('id')->on('states')->nullOnDelete();
            }
            if (Schema::hasTable('local_governments') && ! $this->hasForeign('admin_activity_feed_events', 'local_government_id')) {
                $table->foreign('local_government_id')->references('id')->on('local_governments')->nullOnDelete();
            }
            if (Schema::hasTable('quest_categories') && ! $this->hasForeign('admin_activity_feed_events', 'quest_category_id')) {
                $table->foreign('quest_category_id')->references('id')->on('quest_categories')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_activity_feed_events')) {
            return;
        }

        Schema::table('admin_activity_feed_events', function (Blueprint $table): void {
            foreach (['actor_user_id', 'state_id', 'local_government_id', 'quest_category_id'] as $column) {
                if ($this->hasForeign('admin_activity_feed_events', $column)) {
                    $table->dropForeign([$column]);
                }
            }
        });
    }

    private function hasForeign(string $table, string $column): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        $result = $connection->select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1',
            [$database, $table, $column]
        );

        return $result !== [];
    }
};
