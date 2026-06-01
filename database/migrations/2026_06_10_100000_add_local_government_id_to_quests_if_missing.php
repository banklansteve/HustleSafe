<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026_05_12_200000 only added local_government_id when local_governments already existed.
 * On many installs that table is created later, so the column was never added.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quests') || Schema::hasColumn('quests', 'local_government_id')) {
            return;
        }

        Schema::table('quests', function (Blueprint $table) {
            if (Schema::hasTable('local_governments')) {
                $column = $table->foreignId('local_government_id')->nullable()->constrained('local_governments')->nullOnDelete();
            } else {
                $column = $table->unsignedBigInteger('local_government_id')->nullable();
            }

            if (Schema::hasColumn('quests', 'state_id')) {
                $column->after('state_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('quests') || ! Schema::hasColumn('quests', 'local_government_id')) {
            return;
        }

        Schema::table('quests', function (Blueprint $table) {
            if (Schema::hasTable('local_governments')) {
                $table->dropForeign(['local_government_id']);
            }
            $table->dropColumn('local_government_id');
        });
    }
};
