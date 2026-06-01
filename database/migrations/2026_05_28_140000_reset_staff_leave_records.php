<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_leave_requests') && ! Schema::hasTable('staff_leave_balances')) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('staff_leave_requests')) {
            DB::table('staff_leave_requests')->delete();
        }

        if (Schema::hasTable('staff_leave_balances')) {
            DB::table('staff_leave_balances')->delete();
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Intentionally irreversible — leave records were cleared for a fresh allocation cycle.
    }
};
