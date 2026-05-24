<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('currency', 3)->default('NGN');
            $table->unsignedBigInteger('balance_minor')->default(0);
            $table->unsignedBigInteger('pending_balance_minor')->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('locked_at')->nullable();
            $table->text('lock_reason')->nullable();
            $table->foreignId('locked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_escrows', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 48)->unique();
            $table->foreignId('quest_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('quest_offer_id')->nullable()->constrained('quest_offers')->nullOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('fee_minor')->default(0);
            $table->unsignedBigInteger('released_minor')->default(0);
            $table->unsignedBigInteger('refunded_minor')->default(0);
            $table->char('currency', 3)->default('NGN');
            $table->string('status', 30)->default('pending')->index();
            $table->string('paystack_reference')->nullable()->unique();
            $table->string('paystack_access_code')->nullable();
            $table->timestamp('funded_at')->nullable()->index();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('wallet_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bank_code', 10);
            $table->string('bank_name');
            $table->string('account_number', 20);
            $table->string('account_name');
            $table->string('paystack_recipient_code')->nullable()->index();
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->unique(['user_id', 'account_number', 'bank_code']);
        });

        Schema::create('wallet_withdrawals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 48)->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_bank_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('fee_minor')->default(0);
            $table->string('status', 30)->default('pending')->index();
            $table->string('paystack_transfer_code')->nullable()->index();
            $table->string('paystack_reference')->nullable()->index();
            $table->text('failure_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 48)->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40)->index();
            $table->string('direction', 10)->index();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('fee_minor')->default(0);
            $table->bigInteger('balance_after_minor');
            $table->string('status', 20)->default('completed')->index();
            $table->string('paystack_reference')->nullable()->index();
            $table->string('idempotency_key')->nullable()->unique();
            $table->foreignId('escrow_id')->nullable()->constrained('payment_escrows')->nullOnDelete();
            $table->foreignId('quest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_withdrawal_id')->nullable()->constrained('wallet_withdrawals')->nullOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
            $table->index(['wallet_id', 'type']);
        });

        Schema::create('paystack_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id', 120)->unique();
            $table->string('event_type', 80)->index();
            $table->string('reference')->nullable()->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable()->index();
            $table->string('processing_result', 40)->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('quest_funding_intents')) {
            Schema::table('quest_funding_intents', function (Blueprint $table): void {
                if (! Schema::hasColumn('quest_funding_intents', 'payment_escrow_id')) {
                    $table->foreignId('payment_escrow_id')->nullable()->after('quest_offer_id')->constrained('payment_escrows')->nullOnDelete();
                }
                if (! Schema::hasColumn('quest_funding_intents', 'paystack_reference')) {
                    $table->string('paystack_reference')->nullable()->after('gateway_key')->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('quest_funding_intents')) {
            Schema::table('quest_funding_intents', function (Blueprint $table): void {
                if (Schema::hasColumn('quest_funding_intents', 'payment_escrow_id')) {
                    $table->dropConstrainedForeignId('payment_escrow_id');
                }
                if (Schema::hasColumn('quest_funding_intents', 'paystack_reference')) {
                    $table->dropColumn('paystack_reference');
                }
            });
        }

        Schema::dropIfExists('paystack_webhook_events');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallet_withdrawals');
        Schema::dropIfExists('wallet_bank_accounts');
        Schema::dropIfExists('payment_escrows');
        Schema::dropIfExists('wallets');
    }
};
