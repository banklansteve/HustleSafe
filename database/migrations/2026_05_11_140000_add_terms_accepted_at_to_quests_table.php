<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quests')) {
            return;
        }

        Schema::table('quests', function (Blueprint $table): void {
            if (! Schema::hasColumn('quests', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('quests', function (Blueprint $table): void {
            if (Schema::hasColumn('quests', 'terms_accepted_at')) {
                $table->dropColumn('terms_accepted_at');
            }
        });
    }
};
