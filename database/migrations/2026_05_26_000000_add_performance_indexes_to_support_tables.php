<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add composite index for efficient message fetching by ticket and ordering by id
        // This is critical for the messagesForAdminInbox query which filters by support_ticket_id
        // and orders by id DESC with LIMIT
        if (! Schema::hasIndex('support_ticket_messages', 'support_ticket_messages_ticket_id_id_index')) {
            Schema::table('support_ticket_messages', function (Blueprint $table): void {
                $table->index(['support_ticket_id', 'id'], 'support_ticket_messages_ticket_id_id_index');
            });
        }

        // Add composite index for unread count queries that filter by ticket, sender_type and id
        // Used by ticketDetailPayload unread count calculation
        if (! Schema::hasIndex('support_ticket_messages', 'support_ticket_messages_ticket_sender_type_id_index')) {
            Schema::table('support_ticket_messages', function (Blueprint $table): void {
                $table->index(['support_ticket_id', 'sender_type', 'id'], 'support_ticket_messages_ticket_sender_type_id_index');
            });
        }

        // Add composite index for visibility-filtered queries
        // Used when non-admin users fetch messages with visibility = 'public'
        if (! Schema::hasIndex('support_ticket_messages', 'support_ticket_messages_ticket_visibility_id_index')) {
            Schema::table('support_ticket_messages', function (Blueprint $table): void {
                $table->index(['support_ticket_id', 'visibility', 'id'], 'support_ticket_messages_ticket_visibility_id_index');
            });
        }

        // Ensure support_tickets has proper index for UUID lookups
        // The UUID column should already be unique, but let's ensure there's an index
        if (! Schema::hasIndex('support_tickets', 'support_tickets_uuid_index')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                if (! $this->hasUniqueIndexOnUuid()) {
                    $table->index('uuid', 'support_tickets_uuid_index');
                }
            });
        }

        // Add index for assigned_admin_id lookups (used in queue queries)
        if (! Schema::hasIndex('support_tickets', 'support_tickets_assigned_admin_id_index')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->index('assigned_admin_id', 'support_tickets_assigned_admin_id_index');
            });
        }

        // Add index for user_id lookups (used in customer context queries)
        if (! Schema::hasIndex('support_tickets', 'support_tickets_user_id_index')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->index('user_id', 'support_tickets_user_id_index');
            });
        }

        // Add composite index for handoff lookups by ticket and admin
        if (! Schema::hasIndex('support_ticket_handoffs', 'support_ticket_handoffs_ticket_admin_index')) {
            Schema::table('support_ticket_handoffs', function (Blueprint $table): void {
                // The migration already has this index, but let's ensure it exists
                if (! Schema::hasIndex('support_ticket_handoffs', 'support_ticket_id_from_admin_id')) {
                    $table->index(['support_ticket_id', 'from_admin_id'], 'support_ticket_handoffs_ticket_admin_index');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('support_ticket_messages', function (Blueprint $table): void {
            $table->dropIndex('support_ticket_messages_ticket_id_id_index');
            $table->dropIndex('support_ticket_messages_ticket_sender_type_id_index');
            $table->dropIndex('support_ticket_messages_ticket_visibility_id_index');
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropIndex('support_tickets_uuid_index');
            $table->dropIndex('support_tickets_assigned_admin_id_index');
            $table->dropIndex('support_tickets_user_id_index');
        });

        Schema::table('support_ticket_handoffs', function (Blueprint $table): void {
            $table->dropIndex('support_ticket_handoffs_ticket_admin_index');
        });
    }

    private function hasUniqueIndexOnUuid(): bool
    {
        $indexes = Schema::getIndexListing('support_tickets');

        foreach ($indexes as $index) {
            if (str_contains(strtolower($index), 'uuid') && str_contains(strtolower($index), 'unique')) {
                return true;
            }
        }

        return false;
    }
};