<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quest_delivery_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('revision_number')->default(1);
            $table->text('summary')->nullable();
            $table->string('delivery_url', 2048)->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();

            $table->index(['quest_id', 'submitted_at']);
        });

        Schema::table('quests', function (Blueprint $table): void {
            $table->timestamp('delivery_review_deadline_at')->nullable()->after('delivered_at');
            $table->timestamp('delivery_revision_requested_at')->nullable()->after('delivery_review_deadline_at');
            $table->foreignId('delivery_revision_requested_by')->nullable()->after('delivery_revision_requested_at')->constrained('users')->nullOnDelete();
            $table->text('delivery_revision_note')->nullable()->after('delivery_revision_requested_by');
            $table->unsignedSmallInteger('delivery_submission_count')->default(0)->after('delivery_revision_note');
            $table->unsignedBigInteger('latest_delivery_submission_id')->nullable()->after('delivery_submission_count');
        });

        Schema::table('quests', function (Blueprint $table): void {
            $table->foreign('latest_delivery_submission_id')
                ->references('id')
                ->on('quest_delivery_submissions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quests', function (Blueprint $table): void {
            $table->dropForeign(['latest_delivery_submission_id']);
        });

        Schema::table('quests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('delivery_revision_requested_by');
            $table->dropColumn([
                'delivery_review_deadline_at',
                'delivery_revision_requested_at',
                'delivery_revision_note',
                'delivery_submission_count',
                'latest_delivery_submission_id',
            ]);
        });

        Schema::dropIfExists('quest_delivery_submissions');
    }
};
