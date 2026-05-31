<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quest_journey_surveys', function (Blueprint $table): void {
            $table->json('reminders_sent')->nullable()->after('email_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('quest_journey_surveys', function (Blueprint $table): void {
            $table->dropColumn('reminders_sent');
        });
    }
};
