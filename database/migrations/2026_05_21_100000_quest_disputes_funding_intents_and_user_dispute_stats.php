<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'disputes_lost_count')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedSmallInteger('disputes_lost_count')->default(0);
            });
        }

        if (! Schema::hasTable('quest_funding_intents')) {
            Schema::create('quest_funding_intents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
                $table->foreignId('quest_offer_id')->constrained('quest_offers')->cascadeOnDelete();
                $table->foreignId('initiated_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('quoted_total_minor')->default(0);
                $table->string('status', 32)->default('initiated')->index();
                $table->string('gateway_key', 64)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['quest_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('quest_disputes')) {
            Schema::create('quest_disputes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
                $table->foreignId('quest_offer_id')->constrained('quest_offers')->cascadeOnDelete();
                $table->foreignId('opened_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('reason', 64)->index();
                $table->json('structured_intake')->nullable();
                $table->string('phase', 32)->default('self_resolution')->index();
                $table->string('status', 32)->default('open')->index();
                $table->unsignedTinyInteger('tier')->default(0);
                $table->unsignedTinyInteger('appeals_used')->default(0);
                $table->unsignedBigInteger('disputed_amount_minor')->default(0);
                $table->timestamp('response_required_by')->nullable()->index();
                $table->timestamp('ruling_required_by')->nullable()->index();
                $table->timestamp('escalated_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->string('resolution_outcome', 48)->nullable();
                $table->unsignedTinyInteger('final_client_share_percent')->nullable();
                $table->foreignId('ruling_favoured_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('awaiting_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('client_agrees_resolve_at')->nullable();
                $table->timestamp('freelancer_agrees_resolve_at')->nullable();
                $table->text('opening_summary')->nullable();
                $table->timestamps();

                $table->index(['quest_id', 'status']);
            });
        }

        if (! Schema::hasTable('dispute_events')) {
            Schema::create('dispute_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quest_dispute_id')->constrained('quest_disputes')->cascadeOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 96)->index();
                $table->json('properties')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['quest_dispute_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('dispute_messages')) {
            Schema::create('dispute_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quest_dispute_id')->constrained('quest_disputes')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('kind', 48)->default('narrative')->index();
                $table->text('body')->nullable();
                $table->string('structured_key', 64)->nullable();
                $table->json('structured_payload')->nullable();
                $table->timestamps();

                $table->index(['quest_dispute_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('dispute_settlement_offers')) {
            Schema::create('dispute_settlement_offers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quest_dispute_id')->constrained('quest_disputes')->cascadeOnDelete();
                $table->foreignId('offered_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedTinyInteger('client_share_percent');
                $table->text('note')->nullable();
                $table->string('status', 24)->default('pending')->index();
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();

                $table->index(['quest_dispute_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_settlement_offers');
        Schema::dropIfExists('dispute_messages');
        Schema::dropIfExists('dispute_events');
        Schema::dropIfExists('quest_disputes');
        Schema::dropIfExists('quest_funding_intents');

        if (Schema::hasColumn('users', 'disputes_lost_count')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('disputes_lost_count');
            });
        }
    }
};
