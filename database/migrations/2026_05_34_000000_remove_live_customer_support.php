<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('support_ticket_handoffs');

        if (! Schema::hasTable('support_tickets')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table): void {
            $columns = [
                'customer_username',
                'customer_full_name',
                'chat_status',
                'queued_at',
                'last_activity_at',
                'last_user_activity_at',
                'last_admin_activity_at',
                'user_last_read_message_id',
                'admin_last_read_message_id',
                'rating_stars',
                'rating_score',
                'rating_reaction',
                'rating_comment',
                'feedback_answers',
                'rated_at',
                'rating_email_sent_at',
                'resolution_seconds',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('support_tickets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        // Live customer support was removed intentionally; no rollback.
    }
};
