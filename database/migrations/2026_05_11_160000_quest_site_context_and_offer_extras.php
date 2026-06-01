<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quests')) {
            Schema::table('quests', function (Blueprint $table) {
                if (! Schema::hasColumn('quests', 'site_access_level')) {
                    $table->string('site_access_level', 40)->nullable();
                }
                if (! Schema::hasColumn('quests', 'pets_on_site')) {
                    $table->boolean('pets_on_site')->nullable();
                }
                if (! Schema::hasColumn('quests', 'pets_detail')) {
                    $table->string('pets_detail', 255)->nullable();
                }
            });
        }

        if (! Schema::hasTable('quest_offers')) {
            return;
        }

        Schema::table('quest_offers', function (Blueprint $table) {
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
        if (Schema::hasTable('quest_offers')) {
            Schema::table('quest_offers', function (Blueprint $table) {
                $columns = array_filter([
                    Schema::hasColumn('quest_offers', 'estimated_duration_days') ? 'estimated_duration_days' : null,
                    Schema::hasColumn('quest_offers', 'corrections_included') ? 'corrections_included' : null,
                    Schema::hasColumn('quest_offers', 'corrections_rounds') ? 'corrections_rounds' : null,
                    Schema::hasColumn('quest_offers', 'progress_report_frequency') ? 'progress_report_frequency' : null,
                ]);
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('quests')) {
            Schema::table('quests', function (Blueprint $table) {
                $columns = array_filter([
                    Schema::hasColumn('quests', 'site_access_level') ? 'site_access_level' : null,
                    Schema::hasColumn('quests', 'pets_on_site') ? 'pets_on_site' : null,
                    Schema::hasColumn('quests', 'pets_detail') ? 'pets_detail' : null,
                ]);
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
