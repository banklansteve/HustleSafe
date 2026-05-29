<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_leave_requests', function (Blueprint $table): void {
            $table->string('duration_type', 24)->default('full_day')->after('leave_type');
            $table->unsignedTinyInteger('hours_requested')->nullable()->after('days_requested');
        });

        DB::table('staff_leave_requests')
            ->whereNull('duration_type')
            ->update(['duration_type' => 'full_day']);
    }

    public function down(): void
    {
        Schema::table('staff_leave_requests', function (Blueprint $table): void {
            $table->dropColumn(['duration_type', 'hours_requested']);
        });
    }
};
