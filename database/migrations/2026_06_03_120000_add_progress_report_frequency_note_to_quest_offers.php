<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quest_offers')) {
            return;
        }

        Schema::table('quest_offers', function (Blueprint $table) {
            if (! Schema::hasColumn('quest_offers', 'progress_report_frequency_note')) {
                $table->string('progress_report_frequency_note', 200)->nullable()->after('progress_report_frequency');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('quest_offers')) {
            return;
        }

        Schema::table('quest_offers', function (Blueprint $table) {
            if (Schema::hasColumn('quest_offers', 'progress_report_frequency_note')) {
                $table->dropColumn('progress_report_frequency_note');
            }
        });
    }
};
