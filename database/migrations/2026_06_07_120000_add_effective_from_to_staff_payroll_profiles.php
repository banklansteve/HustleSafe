<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_payroll_profiles', function (Blueprint $table): void {
            $table->date('effective_from')->nullable()->after('payment_frequency');
        });

        DB::table('staff_payroll_profiles')
            ->whereNull('effective_from')
            ->update(['effective_from' => now()->startOfMonth()->toDateString()]);
    }

    public function down(): void
    {
        Schema::table('staff_payroll_profiles', function (Blueprint $table): void {
            $table->dropColumn('effective_from');
        });
    }
};
