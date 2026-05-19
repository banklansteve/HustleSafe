<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'power_hours')) {
                $table->json('power_hours')->nullable()->after('availability');
            }
        });

        Schema::table('user_verifications', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_verifications', 'admin_concern')) {
                $table->text('admin_concern')->nullable()->after('rejection_reason');
            }
            if (! Schema::hasColumn('user_verifications', 'referred_to_admin_id')) {
                $table->foreignId('referred_to_admin_id')->nullable()->after('reviewed_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('user_verifications', 'referred_at')) {
                $table->timestamp('referred_at')->nullable()->after('referred_to_admin_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_verifications', function (Blueprint $table): void {
            if (Schema::hasColumn('user_verifications', 'referred_to_admin_id')) {
                $table->dropConstrainedForeignId('referred_to_admin_id');
            }
            foreach (['referred_at', 'admin_concern'] as $column) {
                if (Schema::hasColumn('user_verifications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'power_hours')) {
                $table->dropColumn('power_hours');
            }
        });
    }
};
