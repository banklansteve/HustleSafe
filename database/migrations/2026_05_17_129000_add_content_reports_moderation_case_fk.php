<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_reports') || ! Schema::hasTable('moderation_cases')) {
            return;
        }

        if (Schema::hasColumn('content_reports', 'moderation_case_id')) {
            return;
        }

        Schema::table('content_reports', function (Blueprint $table): void {
            $table->foreignId('moderation_case_id')->nullable()->constrained('moderation_cases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('content_reports') || ! Schema::hasColumn('content_reports', 'moderation_case_id')) {
            return;
        }

        Schema::table('content_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('moderation_case_id');
        });
    }
};
