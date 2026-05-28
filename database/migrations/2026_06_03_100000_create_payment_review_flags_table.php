<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_review_flags', function (Blueprint $table) {
            $table->id();
            $table->string('anomaly_type', 60)->index();
            $table->string('severity', 10)->index();
            $table->string('anomaly_fingerprint', 120)->index();
            $table->foreignId('payment_escrow_id')->nullable()->constrained('payment_escrows')->nullOnDelete();
            $table->foreignId('quest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->string('transaction_reference', 64)->nullable()->index();
            $table->json('signal_payload');
            $table->foreignId('staff_admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('concern_note', 500);
            $table->string('resolution_status', 30)->default('pending')->index();
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();

            $table->index(['resolution_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_review_flags');
    }
};
