<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::table('staff_leave_requests')->delete();
        DB::table('staff_leave_balances')->delete();

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Intentionally irreversible — leave records were cleared for a fresh allocation cycle.
    }
};
