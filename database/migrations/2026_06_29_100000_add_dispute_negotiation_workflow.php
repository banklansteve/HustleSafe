<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quest_disputes', function (Blueprint $table): void {
            if (! Schema::hasColumn('quest_disputes', 'negotiation_phase')) {
                $table->string('negotiation_phase', 48)->nullable()->after('phase');
            }
            if (! Schema::hasColumn('quest_disputes', 'client_negotiation_attempts')) {
                $table->unsignedTinyInteger('client_negotiation_attempts')->default(0)->after('negotiation_phase');
            }
            if (! Schema::hasColumn('quest_disputes', 'freelancer_negotiation_attempts')) {
                $table->unsignedTinyInteger('freelancer_negotiation_attempts')->default(0)->after('client_negotiation_attempts');
            }
            if (! Schema::hasColumn('quest_disputes', 'active_negotiation_offer_id')) {
                $table->unsignedBigInteger('active_negotiation_offer_id')->nullable()->after('freelancer_negotiation_attempts');
            }
            if (! Schema::hasColumn('quest_disputes', 'binding_mediation_ack_client_at')) {
                $table->timestamp('binding_mediation_ack_client_at')->nullable()->after('freelancer_agrees_resolve_at');
            }
            if (! Schema::hasColumn('quest_disputes', 'binding_mediation_ack_freelancer_at')) {
                $table->timestamp('binding_mediation_ack_freelancer_at')->nullable()->after('binding_mediation_ack_client_at');
            }
            if (! Schema::hasColumn('quest_disputes', 'mutual_agreement_submitted_at')) {
                $table->timestamp('mutual_agreement_submitted_at')->nullable()->after('binding_mediation_ack_freelancer_at');
            }
            if (! Schema::hasColumn('quest_disputes', 'enforcement_pending_at')) {
                $table->timestamp('enforcement_pending_at')->nullable()->after('mutual_agreement_submitted_at');
            }
            if (! Schema::hasColumn('quest_disputes', 'rejection_window_ends_at')) {
                $table->timestamp('rejection_window_ends_at')->nullable()->after('enforcement_pending_at');
            }
            if (! Schema::hasColumn('quest_disputes', 'final_binding_at')) {
                $table->timestamp('final_binding_at')->nullable()->after('rejection_window_ends_at');
            }
        });

        if (! Schema::hasTable('dispute_negotiation_offers')) {
            Schema::create('dispute_negotiation_offers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quest_dispute_id')->constrained('quest_disputes')->cascadeOnDelete();
                $table->foreignId('parent_offer_id')->nullable()->constrained('dispute_negotiation_offers')->nullOnDelete();
                $table->foreignId('offered_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('party_role', 16);
                $table->unsignedTinyInteger('attempt_number')->default(1);
                $table->string('option', 64);
                $table->json('terms');
                $table->string('status', 32)->default('pending');
                $table->boolean('is_final_offer')->default(false);
                $table->string('awaiting_party_role', 16)->nullable();
                $table->timestamp('response_required_by')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->foreignId('responded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('response_action', 32)->nullable();
                $table->timestamps();

                $table->index(['quest_dispute_id', 'status']);
            });
        }

        if (! Schema::hasTable('dispute_appeals')) {
            Schema::create('dispute_appeals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quest_dispute_id')->constrained('quest_disputes')->cascadeOnDelete();
                $table->foreignId('filed_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('party_role', 16);
                $table->text('unfair_reason');
                $table->string('proposed_option', 64)->nullable();
                $table->json('proposed_terms')->nullable();
                $table->text('counter_response')->nullable();
                $table->foreignId('counter_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('counter_responded_at')->nullable();
                $table->string('status', 32)->default('filed');
                $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_outcome_notes')->nullable();
                $table->boolean('upheld_original')->nullable();
                $table->timestamps();

                $table->index(['quest_dispute_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_appeals');
        Schema::dropIfExists('dispute_negotiation_offers');

        Schema::table('quest_disputes', function (Blueprint $table): void {
            foreach ([
                'negotiation_phase',
                'client_negotiation_attempts',
                'freelancer_negotiation_attempts',
                'active_negotiation_offer_id',
                'binding_mediation_ack_client_at',
                'binding_mediation_ack_freelancer_at',
                'mutual_agreement_submitted_at',
                'enforcement_pending_at',
                'rejection_window_ends_at',
                'final_binding_at',
            ] as $column) {
                if (Schema::hasColumn('quest_disputes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
