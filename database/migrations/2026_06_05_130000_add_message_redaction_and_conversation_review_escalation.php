<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['quest_conversation_messages', 'proposal_clarification_messages'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'body_original')) {
                    $table->text('body_original')->nullable()->after('body');
                }
                if (! Schema::hasColumn($tableName, 'is_redacted')) {
                    $table->boolean('is_redacted')->default(false)->after('body_original');
                }
                if (! Schema::hasColumn($tableName, 'redaction_label')) {
                    $table->string('redaction_label', 64)->nullable()->after('is_redacted');
                }
            });
        }

        if (Schema::hasTable('conversation_thread_reviews')) {
            Schema::table('conversation_thread_reviews', function (Blueprint $table): void {
                if (! Schema::hasColumn('conversation_thread_reviews', 'super_admin_escalated_at')) {
                    $table->timestamp('super_admin_escalated_at')->nullable()->after('assigned_staff_id');
                    $table->foreignId('super_admin_escalation_by')->nullable()->after('super_admin_escalated_at')->constrained('users')->nullOnDelete();
                    $table->string('super_admin_escalation_note', 2000)->nullable()->after('super_admin_escalation_by');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['quest_conversation_messages', 'proposal_clarification_messages'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['body_original', 'is_redacted', 'redaction_label']);
            });
        }

        if (Schema::hasTable('conversation_thread_reviews')) {
            Schema::table('conversation_thread_reviews', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('super_admin_escalation_by');
                $table->dropColumn(['super_admin_escalated_at', 'super_admin_escalation_note']);
            });
        }
    }
};
