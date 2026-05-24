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
            if (! Schema::hasColumn('support_tickets', 'customer_username')) {
                $table->string('customer_username')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('support_tickets', 'customer_full_name')) {
                $table->string('customer_full_name')->nullable()->after('customer_username');
            }
            if (! Schema::hasColumn('support_tickets', 'chat_status')) {
                $table->string('chat_status', 24)->default('queued')->index()->after('status');
            }
            if (! Schema::hasColumn('support_tickets', 'queued_at')) {
                $table->timestamp('queued_at')->nullable()->index()->after('opened_at');
            }
            if (! Schema::hasColumn('support_tickets', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->index();
            }
            if (! Schema::hasColumn('support_tickets', 'last_user_activity_at')) {
                $table->timestamp('last_user_activity_at')->nullable();
            }
            if (! Schema::hasColumn('support_tickets', 'last_admin_activity_at')) {
                $table->timestamp('last_admin_activity_at')->nullable();
            }
            if (! Schema::hasColumn('support_tickets', 'user_last_read_message_id')) {
                $table->unsignedBigInteger('user_last_read_message_id')->nullable();
            }
            if (! Schema::hasColumn('support_tickets', 'admin_last_read_message_id')) {
                $table->unsignedBigInteger('admin_last_read_message_id')->nullable();
            }
            if (! Schema::hasColumn('support_tickets', 'rating_stars')) {
                $table->unsignedTinyInteger('rating_stars')->nullable();
            }
            if (! Schema::hasColumn('support_tickets', 'rating_score')) {
                $table->unsignedTinyInteger('rating_score')->nullable();
            }
            if (! Schema::hasColumn('support_tickets', 'rating_reaction')) {
                $table->string('rating_reaction', 32)->nullable();
            }
            if (! Schema::hasColumn('support_tickets', 'rating_comment')) {
                $table->text('rating_comment')->nullable();
            }
            if (! Schema::hasColumn('support_tickets', 'feedback_answers')) {
                $table->json('feedback_answers')->nullable();
            }
            if (! Schema::hasColumn('support_tickets', 'rated_at')) {
                $table->timestamp('rated_at')->nullable();
            }
            if (! Schema::hasColumn('support_tickets', 'rating_email_sent_at')) {
                $table->timestamp('rating_email_sent_at')->nullable()->index();
            }
            if (! Schema::hasColumn('support_tickets', 'resolution_seconds')) {
                $table->unsignedInteger('resolution_seconds')->nullable();
            }
        });

        if (! Schema::hasTable('support_ticket_handoffs')) {
            Schema::create('support_ticket_handoffs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                $table->foreignId('from_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('to_admin_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('reassigned_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('handoff_message_id')->nullable();
                $table->timestamps();

                $table->index(['support_ticket_id', 'from_admin_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_handoffs');

        if (! Schema::hasTable('support_tickets')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table): void {
            foreach ([
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
            ] as $column) {
                if (Schema::hasColumn('support_tickets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
