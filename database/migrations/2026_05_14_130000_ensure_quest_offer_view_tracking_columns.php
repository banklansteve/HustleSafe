<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures proposal view-tracking columns exist (e.g. if an older partial migration ran).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quest_offers')) {
            return;
        }

        Schema::table('quest_offers', function (Blueprint $table): void {
            if (! Schema::hasColumn('quest_offers', 'client_view_count')) {
                $table->unsignedInteger('client_view_count')->default(0);
            }
            if (! Schema::hasColumn('quest_offers', 'last_client_view_at')) {
                $table->timestamp('last_client_view_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        //
    }
};
