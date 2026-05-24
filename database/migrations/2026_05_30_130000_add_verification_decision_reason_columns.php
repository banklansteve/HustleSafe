<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_verifications')) {
            return;
        }

        Schema::table('user_verifications', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_verifications', 'decision_reason_code')) {
                $table->string('decision_reason_code', 40)->nullable()->after('rejection_reason');
            }
            if (! Schema::hasColumn('user_verifications', 'decision_reason_note')) {
                $table->text('decision_reason_note')->nullable()->after('decision_reason_code');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_verifications')) {
            return;
        }

        Schema::table('user_verifications', function (Blueprint $table): void {
            if (Schema::hasColumn('user_verifications', 'decision_reason_note')) {
                $table->dropColumn('decision_reason_note');
            }
            if (Schema::hasColumn('user_verifications', 'decision_reason_code')) {
                $table->dropColumn('decision_reason_code');
            }
        });
    }
};
