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

        if (Schema::hasColumn('quest_offers', 'freelancer_edit_deadline_at')) {
            return;
        }

        Schema::table('quest_offers', function (Blueprint $table): void {
            if (Schema::hasColumn('quest_offers', 'last_client_view_at')) {
                $table->timestamp('freelancer_edit_deadline_at')->nullable()->after('last_client_view_at');
            } else {
                $table->timestamp('freelancer_edit_deadline_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('quest_offers')) {
            return;
        }

        if (! Schema::hasColumn('quest_offers', 'freelancer_edit_deadline_at')) {
            return;
        }

        Schema::table('quest_offers', function (Blueprint $table): void {
            $table->dropColumn('freelancer_edit_deadline_at');
        });
    }
};
