<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_journal_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 64)->unique();
            $table->string('event_type', 64);
            $table->string('idempotency_key', 191)->unique();
            $table->foreignId('payment_escrow_id')->nullable()->constrained('payment_escrows')->nullOnDelete();
            $table->foreignId('quest_id')->nullable()->constrained('quests')->nullOnDelete();
            $table->foreignId('quest_contract_id')->nullable()->constrained('quest_contracts')->nullOnDelete();
            $table->foreignId('wallet_withdrawal_id')->nullable()->constrained('wallet_withdrawals')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('freelancer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('paystack_reference', 120)->nullable()->index();
            $table->string('description', 500)->nullable();
            $table->string('created_by_process', 120);
            $table->foreignId('reverses_batch_id')->nullable()->constrained('ledger_journal_batches')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('batch_id')->constrained('ledger_journal_batches')->cascadeOnDelete();
            $table->string('ledger_account', 64);
            $table->string('side', 8);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('NGN');
            $table->foreignId('payment_escrow_id')->nullable()->constrained('payment_escrows')->nullOnDelete();
            $table->foreignId('quest_id')->nullable()->constrained('quests')->nullOnDelete();
            $table->foreignId('wallet_withdrawal_id')->nullable()->constrained('wallet_withdrawals')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('freelancer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('paystack_reference', 120)->nullable()->index();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ledger_account', 'occurred_at']);
            $table->index(['payment_escrow_id', 'occurred_at']);
        });

        Schema::create('financial_escrow_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('escrow_reference', 64)->unique();
            $table->foreignId('payment_escrow_id')->unique()->constrained('payment_escrows')->cascadeOnDelete();
            $table->foreignId('quest_id')->constrained('quests')->cascadeOnDelete();
            $table->foreignId('quest_contract_id')->nullable()->constrained('quest_contracts')->nullOnDelete();
            $table->string('contract_reference', 64)->nullable()->index();
            $table->string('quest_title', 255);
            $table->foreignId('quest_category_id')->nullable()->constrained('quest_categories')->nullOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('client_name', 160);
            $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
            $table->string('freelancer_name', 160);
            $table->unsignedBigInteger('gross_contract_value_minor');
            $table->unsignedBigInteger('total_funded_minor');
            $table->decimal('platform_fee_percent', 5, 2);
            $table->unsignedBigInteger('platform_fee_minor')->default(0);
            $table->decimal('vat_percent', 5, 2)->default(7.5);
            $table->unsignedBigInteger('vat_minor')->default(0);
            $table->unsignedBigInteger('freelancer_net_minor')->default(0);
            $table->string('gateway_name', 40)->default('paystack');
            $table->string('paystack_reference', 120)->nullable()->index();
            $table->timestamp('funded_at')->nullable();
            $table->string('status', 32)->default('held')->index();
            $table->string('release_trigger_type', 64)->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('wallet_credit_reference', 120)->nullable();
            $table->timestamp('fee_recognised_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'funded_at']);
        });

        Schema::create('financial_reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 24)->default('running');
            $table->unsignedInteger('records_processed')->default(0);
            $table->unsignedInteger('exceptions_found')->default(0);
            $table->json('checks')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_reconciliation_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('first_run_id')->nullable()->constrained('financial_reconciliation_runs')->nullOnDelete();
            $table->foreignId('latest_run_id')->nullable()->constrained('financial_reconciliation_runs')->nullOnDelete();
            $table->string('type', 64);
            $table->string('status', 32)->default('open')->index();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_escrow_id')->nullable()->constrained('payment_escrows')->nullOnDelete();
            $table->string('paystack_reference', 120)->nullable()->index();
            $table->bigInteger('variance_minor')->nullable();
            $table->string('title', 255);
            $table->text('description');
            $table->text('investigation_notes')->nullable();
            $table->text('resolution_description')->nullable();
            $table->timestamp('first_detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('escalated_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });

        Schema::create('vat_remittances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('quarter_label', 32);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('amount_minor');
            $table->string('remittance_reference', 120);
            $table->timestamp('remitted_at');
            $table->foreignId('recorded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['quarter_label', 'remittance_reference']);
        });

        Schema::create('financial_audit_reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 64);
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('generated_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('generated_at');
            $table->string('csv_path', 500)->nullable();
            $table->string('pdf_path', 500)->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->index(['type', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_audit_reports');
        Schema::dropIfExists('vat_remittances');
        Schema::dropIfExists('financial_reconciliation_exceptions');
        Schema::dropIfExists('financial_reconciliation_runs');
        Schema::dropIfExists('financial_escrow_records');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_journal_batches');
    }
};
