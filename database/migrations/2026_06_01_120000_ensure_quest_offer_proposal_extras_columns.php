<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures proposal extra columns exist on quest_offers.
 *
 * 2026_05_11_160000_quest_site_context_and_offer_extras may have run before
 * quest_offers was created, so those columns were never applied.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quest_offers')) {
            return;
        }

        Schema::table('quest_offers', function (Blueprint $table): void {
            if (! Schema::hasColumn('quest_offers', 'estimated_duration_days')) {
                $table->unsignedSmallInteger('estimated_duration_days')->nullable();
            }
            if (! Schema::hasColumn('quest_offers', 'corrections_included')) {
                $table->boolean('corrections_included')->default(false);
            }
            if (! Schema::hasColumn('quest_offers', 'corrections_rounds')) {
                $table->unsignedTinyInteger('corrections_rounds')->nullable();
            }
            if (! Schema::hasColumn('quest_offers', 'progress_report_frequency')) {
                $table->string('progress_report_frequency', 32)->nullable();
            }
        });
    }

    public function down(): void
    {
        //
    }
};
