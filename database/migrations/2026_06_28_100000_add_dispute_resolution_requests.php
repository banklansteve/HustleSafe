<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dispute_resolution_requests')) {
            Schema::create('dispute_resolution_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quest_dispute_id')->constrained('quest_disputes')->cascadeOnDelete();
                $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('party_role', 16);
                $table->string('option', 48);
                $table->json('terms')->nullable();
                $table->string('status', 24)->default('pending');
                $table->foreignId('matched_request_id')->nullable()->constrained('dispute_resolution_requests')->nullOnDelete();
                $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();
                $table->timestamps();

                $table->index(['quest_dispute_id', 'status']);
                $table->index(['quest_dispute_id', 'option']);
            });
        }

        if (Schema::hasTable('dispute_assessments') && ! Schema::hasColumn('dispute_assessments', 'alternate_recommendations')) {
            Schema::table('dispute_assessments', function (Blueprint $table): void {
                $table->json('alternate_recommendations')->nullable()->after('recommendation');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dispute_assessments') && Schema::hasColumn('dispute_assessments', 'alternate_recommendations')) {
            Schema::table('dispute_assessments', function (Blueprint $table): void {
                $table->dropColumn('alternate_recommendations');
            });
        }

        Schema::dropIfExists('dispute_resolution_requests');
    }
};
