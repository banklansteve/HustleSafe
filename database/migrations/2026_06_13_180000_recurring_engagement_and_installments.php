<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->string('engagement_mode', 32)->default('one_time')->after('project_type');
            $table->string('installment_frequency', 16)->nullable()->after('engagement_mode');
            $table->unsignedSmallInteger('contract_duration_months')->nullable()->after('installment_frequency');
            $table->unsignedSmallInteger('installment_count')->nullable()->after('contract_duration_months');
            $table->unsignedBigInteger('installment_amount_minor')->nullable()->after('installment_count');
            $table->timestamp('contract_starts_at')->nullable()->after('installment_amount_minor');
            $table->timestamp('contract_ends_at')->nullable()->after('contract_starts_at');
            $table->unsignedBigInteger('current_installment_id')->nullable()->after('contract_ends_at');
        });

        Schema::create('quest_payment_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->timestamp('period_start_at');
            $table->timestamp('period_end_at');
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 32)->default('pending');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('delivery_review_deadline_at')->nullable();
            $table->timestamp('delivery_revision_requested_at')->nullable();
            $table->text('delivery_revision_note')->nullable();
            $table->timestamp('delivery_acknowledged_at')->nullable();
            $table->foreignId('latest_delivery_submission_id')->nullable()->constrained('quest_delivery_submissions')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->unique(['quest_id', 'installment_number']);
            $table->index(['quest_id', 'status']);
        });

        Schema::table('quests', function (Blueprint $table) {
            $table->foreign('current_installment_id')
                ->references('id')
                ->on('quest_payment_installments')
                ->nullOnDelete();
        });

        Schema::table('quest_offers', function (Blueprint $table) {
            $table->boolean('accepts_installment_terms')->default(false)->after('corrections_rounds');
        });
    }

    public function down(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->dropForeign(['current_installment_id']);
        });

        Schema::table('quest_offers', function (Blueprint $table) {
            $table->dropColumn('accepts_installment_terms');
        });

        Schema::dropIfExists('quest_payment_installments');

        Schema::table('quests', function (Blueprint $table) {
            $table->dropColumn([
                'engagement_mode',
                'installment_frequency',
                'contract_duration_months',
                'installment_count',
                'installment_amount_minor',
                'contract_starts_at',
                'contract_ends_at',
                'current_installment_id',
            ]);
        });
    }
};
