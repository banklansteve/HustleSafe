<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_trust_metrics', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_trust_metrics', 'client_award_cancellation_count')) {
                $table->unsignedSmallInteger('client_award_cancellation_count')->default(0)->after('client_proposal_ghost_strikes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_trust_metrics', function (Blueprint $table): void {
            if (Schema::hasColumn('user_trust_metrics', 'client_award_cancellation_count')) {
                $table->dropColumn('client_award_cancellation_count');
            }
        });
    }
};
