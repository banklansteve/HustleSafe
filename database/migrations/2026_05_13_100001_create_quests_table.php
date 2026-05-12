<?php

use App\Enums\QuestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('freelancer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            /** @see QuestStatus */
            $table->string('status', 40)->index();
            /** Stored in minor units (e.g. kobo) for NG context */
            $table->unsignedBigInteger('budget_amount_minor')->nullable();
            $table->unsignedBigInteger('paid_out_minor')->default(0);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('completed_on_time')->nullable();
            $table->boolean('dispute_opened')->default(false);
            /** How the quest ended when not a clean completion — drives partial reviews */
            $table->string('closure_type', 40)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quests');
    }
};
