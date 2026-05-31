<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quest_contracts', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_code', 32)->unique();
            $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quest_offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('pending_escrow')->index();
            $table->timestamp('generated_at');
            $table->timestamp('escrow_expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('escrow_funding_reference', 64)->nullable();
            $table->timestamp('escrow_funded_at')->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('agreed_delivery_date')->nullable();
            $table->unsignedSmallInteger('revisions_included')->default(0);
            $table->unsignedSmallInteger('revisions_used')->default(0);
            $table->unsignedSmallInteger('amendment_count')->default(0);
            $table->json('parties_snapshot');
            $table->json('quest_snapshot');
            $table->json('financial_snapshot');
            $table->json('timeline_snapshot');
            $table->json('revision_policy_snapshot');
            $table->json('platform_terms_snapshot');
            $table->json('signatures_snapshot');
            $table->json('current_terms_snapshot')->nullable();
            $table->foreignId('active_dispute_id')->nullable()->constrained('quest_disputes')->nullOnDelete();
            $table->boolean('flagged_for_review')->default(false);
            $table->text('flagged_for_review_reason')->nullable();
            $table->foreignId('flagged_for_review_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('flagged_for_review_at')->nullable();
            $table->timestamps();

            $table->unique(['quest_id', 'quest_offer_id']);
        });

        Schema::create('quest_contract_deliverables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quest_contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('quest_contract_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quest_contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('name');
            $table->string('deliverable_reference')->nullable();
            $table->unsignedBigInteger('value_minor')->default(0);
            $table->date('deadline_date')->nullable();
            $table->timestamps();
        });

        Schema::create('quest_contract_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quest_contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('amendment_number');
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('amendment_type', 32);
            $table->text('description');
            $table->text('reason');
            $table->string('original_value')->nullable();
            $table->string('new_value')->nullable();
            $table->string('status', 16)->default('pending');
            $table->text('response_note')->nullable();
            $table->foreignId('responded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->json('applied_terms_delta')->nullable();
            $table->timestamps();
        });

        Schema::create('quest_contract_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quest_contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 64)->index();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_contract_events');
        Schema::dropIfExists('quest_contract_amendments');
        Schema::dropIfExists('quest_contract_milestones');
        Schema::dropIfExists('quest_contract_deliverables');
        Schema::dropIfExists('quest_contracts');
    }
};
