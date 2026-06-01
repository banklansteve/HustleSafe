<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quests')) {
            Schema::table('quests', function (Blueprint $table): void {
                if (! Schema::hasColumn('quests', 'refunded_minor')) {
                    $table->unsignedBigInteger('refunded_minor')->default(0);
                }
                if (! Schema::hasColumn('quests', 'escrow_held_at')) {
                    $table->timestamp('escrow_held_at')->nullable()->index();
                }
                if (! Schema::hasColumn('quests', 'escrow_hold_reason')) {
                    $table->text('escrow_hold_reason')->nullable();
                }
                if (! Schema::hasColumn('quests', 'escrow_hold_expected_resolution_at')) {
                    $table->timestamp('escrow_hold_expected_resolution_at')->nullable();
                }
                if (! Schema::hasColumn('quests', 'escrow_frozen_at')) {
                    $table->timestamp('escrow_frozen_at')->nullable()->index();
                }
                if (! Schema::hasColumn('quests', 'escrow_freeze_reason')) {
                    $table->text('escrow_freeze_reason')->nullable();
                }
            });
        }

        if (Schema::hasTable('admin_financial_ledger_entries')) {
            return;
        }

        Schema::create('admin_financial_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 40)->unique();
            $table->foreignId('quest_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('quest_offer_id')->nullable()->index();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('freelancer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 50)->index();
            $table->string('direction', 50)->index();
            $table->string('source', 30)->default('system')->index();
            $table->string('status', 30)->default('posted')->index();
            $table->string('description');
            $table->unsignedBigInteger('gross_amount_minor')->default(0);
            $table->unsignedBigInteger('fee_amount_minor')->default(0);
            $table->bigInteger('net_amount_minor')->default(0);
            $table->bigInteger('balance_after_minor')->default(0);
            $table->string('paystack_reference')->nullable()->index();
            $table->text('admin_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['quest_id', 'occurred_at']);
        });

        if (Schema::hasTable('quest_offers')) {
            Schema::table('admin_financial_ledger_entries', function (Blueprint $table): void {
                $table->foreign('quest_offer_id')->references('id')->on('quest_offers')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_financial_ledger_entries');

        if (! Schema::hasTable('quests')) {
            return;
        }

        Schema::table('quests', function (Blueprint $table): void {
            foreach ([
                'escrow_freeze_reason',
                'escrow_frozen_at',
                'escrow_hold_expected_resolution_at',
                'escrow_hold_reason',
                'escrow_held_at',
                'refunded_minor',
            ] as $column) {
                if (Schema::hasColumn('quests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
