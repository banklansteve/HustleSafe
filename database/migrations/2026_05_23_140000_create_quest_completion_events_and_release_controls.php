<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quest_completion_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quest_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 64)->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['quest_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
        });

        Schema::table('quests', function (Blueprint $table): void {
            $table->timestamp('delivery_acknowledged_at')->nullable()->after('escrow_funded_at');
            $table->foreignId('delivery_acknowledged_by')->nullable()->after('delivery_acknowledged_at')->constrained('users')->nullOnDelete();
            $table->timestamp('release_authorized_at')->nullable()->after('delivery_acknowledged_by');
            $table->foreignId('release_authorized_by')->nullable()->after('release_authorized_at')->constrained('users')->nullOnDelete();
            $table->timestamp('release_hold_until')->nullable()->after('release_authorized_by');
            $table->text('release_hold_reason')->nullable()->after('release_hold_until');
            $table->foreignId('release_hold_by')->nullable()->after('release_hold_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('funds_released_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('quests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('delivery_acknowledged_by');
            $table->dropConstrainedForeignId('release_authorized_by');
            $table->dropConstrainedForeignId('release_hold_by');
            $table->dropColumn([
                'delivery_acknowledged_at',
                'release_authorized_at',
                'release_hold_until',
                'release_hold_reason',
                'funds_released_at',
            ]);
        });

        Schema::dropIfExists('quest_completion_events');
    }
};
