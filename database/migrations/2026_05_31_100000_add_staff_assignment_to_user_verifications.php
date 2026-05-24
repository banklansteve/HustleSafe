<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_verifications', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_verifications', 'assigned_staff_id')) {
                $table->foreignId('assigned_staff_id')->nullable()->after('reviewed_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('user_verifications', 'staff_assigned_at')) {
                $table->timestamp('staff_assigned_at')->nullable()->after('assigned_staff_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_verifications', function (Blueprint $table): void {
            if (Schema::hasColumn('user_verifications', 'assigned_staff_id')) {
                $table->dropConstrainedForeignId('assigned_staff_id');
            }
            if (Schema::hasColumn('user_verifications', 'staff_assigned_at')) {
                $table->dropColumn('staff_assigned_at');
            }
        });
    }
};
