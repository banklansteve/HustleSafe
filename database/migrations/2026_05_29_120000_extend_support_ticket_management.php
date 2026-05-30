<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_tickets')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_tickets', 'ticket_reference')) {
                $table->string('ticket_reference', 32)->nullable()->unique()->after('uuid');
            }
            if (! Schema::hasColumn('support_tickets', 'issue_group')) {
                $table->string('issue_group', 100)->nullable()->after('category');
            }
            if (! Schema::hasColumn('support_tickets', 'internal_notes')) {
                $table->text('internal_notes')->nullable()->after('description');
            }
            if (! Schema::hasColumn('support_tickets', 'expected_resolution_at')) {
                $table->timestamp('expected_resolution_at')->nullable()->after('opened_at');
            }
            if (! Schema::hasColumn('support_tickets', 'sla_breached')) {
                $table->boolean('sla_breached')->default(false)->after('expected_resolution_at');
            }
            if (! Schema::hasColumn('support_tickets', 'sla_overdue_at')) {
                $table->timestamp('sla_overdue_at')->nullable()->after('sla_breached');
            }
            if (! Schema::hasColumn('support_tickets', 'sla_override_reason')) {
                $table->text('sla_override_reason')->nullable()->after('sla_overdue_at');
            }
            if (! Schema::hasColumn('support_tickets', 'sla_override_by_user_id')) {
                $table->foreignId('sla_override_by_user_id')->nullable()->after('sla_override_reason')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('support_tickets', 'action_items')) {
                $table->json('action_items')->nullable()->after('internal_notes');
            }
            if (! Schema::hasColumn('support_tickets', 'merged_into_support_ticket_id')) {
                $table->foreignId('merged_into_support_ticket_id')->nullable()->after('action_items')->constrained('support_tickets')->nullOnDelete();
            }
            if (! Schema::hasColumn('support_tickets', 'in_progress_at')) {
                $table->timestamp('in_progress_at')->nullable()->after('opened_at');
            }
        });

        if (! Schema::hasTable('support_ticket_issue_groups')) {
            Schema::create('support_ticket_issue_groups', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 80)->unique();
                $table->string('label');
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_ticket_activities')) {
            Schema::create('support_ticket_activities', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_role', 40)->nullable();
                $table->string('event_type', 80);
                $table->string('summary');
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();

                $table->index(['support_ticket_id', 'occurred_at']);
            });
        }

        if (! Schema::hasTable('support_ticket_email_logs')) {
            Schema::create('support_ticket_email_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
                $table->string('recipient_email');
                $table->string('subject');
                $table->string('event_type', 80);
                $table->json('metadata')->nullable();
                $table->timestamp('sent_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_email_logs');
        Schema::dropIfExists('support_ticket_activities');
        Schema::dropIfExists('support_ticket_issue_groups');

        if (! Schema::hasTable('support_tickets')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table): void {
            foreach ([
                'merged_into_support_ticket_id',
                'sla_override_by_user_id',
            ] as $column) {
                if (Schema::hasColumn('support_tickets', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach ([
                'ticket_reference',
                'issue_group',
                'internal_notes',
                'expected_resolution_at',
                'sla_breached',
                'sla_overdue_at',
                'sla_override_reason',
                'action_items',
                'in_progress_at',
            ] as $column) {
                if (Schema::hasColumn('support_tickets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
