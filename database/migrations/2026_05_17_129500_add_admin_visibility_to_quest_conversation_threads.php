<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quest_conversation_threads')) {
            return;
        }

        Schema::table('quest_conversation_threads', function (Blueprint $table): void {
            if (! Schema::hasColumn('quest_conversation_threads', 'admin_hidden_at')) {
                $table->timestamp('admin_hidden_at')->nullable()->index();
            }
            if (! Schema::hasColumn('quest_conversation_threads', 'admin_deleted_at')) {
                $table->timestamp('admin_deleted_at')->nullable()->index();
            }
            if (! Schema::hasColumn('quest_conversation_threads', 'admin_visibility_reason')) {
                $table->text('admin_visibility_reason')->nullable();
            }
            if (! Schema::hasColumn('quest_conversation_threads', 'admin_visibility_changed_by')) {
                $table->foreignId('admin_visibility_changed_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('quest_conversation_threads')) {
            return;
        }

        Schema::table('quest_conversation_threads', function (Blueprint $table): void {
            if (Schema::hasColumn('quest_conversation_threads', 'admin_visibility_changed_by')) {
                $table->dropConstrainedForeignId('admin_visibility_changed_by');
            }
            foreach (['admin_visibility_reason', 'admin_deleted_at', 'admin_hidden_at'] as $column) {
                if (Schema::hasColumn('quest_conversation_threads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
