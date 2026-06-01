<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('support_ticket_messages')) {
            if (! Schema::hasIndex('support_ticket_messages', 'support_ticket_messages_ticket_id_id_index')) {
                Schema::table('support_ticket_messages', function (Blueprint $table): void {
                    $table->index(['support_ticket_id', 'id'], 'support_ticket_messages_ticket_id_id_index');
                });
            }

            if (! Schema::hasIndex('support_ticket_messages', 'support_ticket_messages_ticket_sender_type_id_index')) {
                Schema::table('support_ticket_messages', function (Blueprint $table): void {
                    $table->index(['support_ticket_id', 'sender_type', 'id'], 'support_ticket_messages_ticket_sender_type_id_index');
                });
            }

            if (! Schema::hasIndex('support_ticket_messages', 'support_ticket_messages_ticket_visibility_id_index')) {
                Schema::table('support_ticket_messages', function (Blueprint $table): void {
                    $table->index(['support_ticket_id', 'visibility', 'id'], 'support_ticket_messages_ticket_visibility_id_index');
                });
            }
        }

        if (Schema::hasTable('support_tickets')) {
            if (! Schema::hasIndex('support_tickets', 'support_tickets_uuid_index')) {
                Schema::table('support_tickets', function (Blueprint $table): void {
                    if (! $this->hasUniqueIndexOnUuid()) {
                        $table->index('uuid', 'support_tickets_uuid_index');
                    }
                });
            }

            if (! Schema::hasIndex('support_tickets', 'support_tickets_assigned_admin_id_index')) {
                Schema::table('support_tickets', function (Blueprint $table): void {
                    $table->index('assigned_admin_id', 'support_tickets_assigned_admin_id_index');
                });
            }

            if (! Schema::hasIndex('support_tickets', 'support_tickets_user_id_index')) {
                Schema::table('support_tickets', function (Blueprint $table): void {
                    $table->index('user_id', 'support_tickets_user_id_index');
                });
            }
        }

        if (Schema::hasTable('support_ticket_handoffs')
            && ! Schema::hasIndex('support_ticket_handoffs', 'support_ticket_handoffs_ticket_admin_index')
            && ! Schema::hasIndex('support_ticket_handoffs', 'support_ticket_id_from_admin_id')) {
            Schema::table('support_ticket_handoffs', function (Blueprint $table): void {
                $table->index(['support_ticket_id', 'from_admin_id'], 'support_ticket_handoffs_ticket_admin_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('support_ticket_messages')) {
            Schema::table('support_ticket_messages', function (Blueprint $table): void {
                if (Schema::hasIndex('support_ticket_messages', 'support_ticket_messages_ticket_id_id_index')) {
                    $table->dropIndex('support_ticket_messages_ticket_id_id_index');
                }
                if (Schema::hasIndex('support_ticket_messages', 'support_ticket_messages_ticket_sender_type_id_index')) {
                    $table->dropIndex('support_ticket_messages_ticket_sender_type_id_index');
                }
                if (Schema::hasIndex('support_ticket_messages', 'support_ticket_messages_ticket_visibility_id_index')) {
                    $table->dropIndex('support_ticket_messages_ticket_visibility_id_index');
                }
            });
        }

        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                if (Schema::hasIndex('support_tickets', 'support_tickets_uuid_index')) {
                    $table->dropIndex('support_tickets_uuid_index');
                }
                if (Schema::hasIndex('support_tickets', 'support_tickets_assigned_admin_id_index')) {
                    $table->dropIndex('support_tickets_assigned_admin_id_index');
                }
                if (Schema::hasIndex('support_tickets', 'support_tickets_user_id_index')) {
                    $table->dropIndex('support_tickets_user_id_index');
                }
            });
        }

        if (Schema::hasTable('support_ticket_handoffs')
            && Schema::hasIndex('support_ticket_handoffs', 'support_ticket_handoffs_ticket_admin_index')) {
            Schema::table('support_ticket_handoffs', function (Blueprint $table): void {
                $table->dropIndex('support_ticket_handoffs_ticket_admin_index');
            });
        }
    }

    private function hasUniqueIndexOnUuid(): bool
    {
        if (! Schema::hasTable('support_tickets')) {
            return false;
        }

        $indexes = Schema::getIndexListing('support_tickets');

        foreach ($indexes as $index) {
            if (str_contains(strtolower($index), 'uuid') && str_contains(strtolower($index), 'unique')) {
                return true;
            }
        }

        return false;
    }
};
