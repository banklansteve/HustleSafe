<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quest_disputes', function (Blueprint $table): void {
            if (! Schema::hasColumn('quest_disputes', 'assigned_staff_id')) {
                $table->foreignId('assigned_staff_id')->nullable()->after('opened_by_user_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('quest_disputes', 'staff_claimed_at')) {
                $table->timestamp('staff_claimed_at')->nullable()->after('assigned_staff_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quest_disputes', function (Blueprint $table): void {
            if (Schema::hasColumn('quest_disputes', 'assigned_staff_id')) {
                $table->dropConstrainedForeignId('assigned_staff_id');
            }
            if (Schema::hasColumn('quest_disputes', 'staff_claimed_at')) {
                $table->dropColumn('staff_claimed_at');
            }
        });
    }
};
