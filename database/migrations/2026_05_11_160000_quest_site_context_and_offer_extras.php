<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            if (! Schema::hasColumn('quests', 'site_access_level')) {
                $table->string('site_access_level', 40)->nullable()->after('site_visits_allowed');
            }
            if (! Schema::hasColumn('quests', 'pets_on_site')) {
                $table->boolean('pets_on_site')->nullable()->after('site_access_level');
            }
            if (! Schema::hasColumn('quests', 'pets_detail')) {
                $table->string('pets_detail', 255)->nullable()->after('pets_on_site');
            }
        });

        Schema::table('quest_offers', function (Blueprint $table) {
            if (! Schema::hasColumn('quest_offers', 'estimated_duration_days')) {
                $table->unsignedSmallInteger('estimated_duration_days')->nullable()->after('planned_finish_date');
            }
            if (! Schema::hasColumn('quest_offers', 'corrections_included')) {
                $table->boolean('corrections_included')->default(false)->after('estimated_duration_days');
            }
            if (! Schema::hasColumn('quest_offers', 'corrections_rounds')) {
                $table->unsignedTinyInteger('corrections_rounds')->nullable()->after('corrections_included');
            }
            if (! Schema::hasColumn('quest_offers', 'progress_report_frequency')) {
                $table->string('progress_report_frequency', 32)->nullable()->after('corrections_rounds');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quest_offers', function (Blueprint $table) {
            $table->dropColumn([
                'estimated_duration_days',
                'corrections_included',
                'corrections_rounds',
                'progress_report_frequency',
            ]);
        });

        Schema::table('quests', function (Blueprint $table) {
            $table->dropColumn(['site_access_level', 'pets_on_site', 'pets_detail']);
        });
    }
};
