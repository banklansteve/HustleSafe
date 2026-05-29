<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_payroll_adjustments', function (Blueprint $table): void {
            $table->string('deduction_mode', 20)->nullable()->after('type');
            $table->string('deduction_basis', 20)->nullable()->after('deduction_mode');
            $table->decimal('deduction_percentage', 8, 4)->nullable()->after('deduction_basis');
            $table->decimal('deduction_custom_base_amount', 15, 2)->nullable()->after('deduction_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('staff_payroll_adjustments', function (Blueprint $table): void {
            $table->dropColumn([
                'deduction_mode',
                'deduction_basis',
                'deduction_percentage',
                'deduction_custom_base_amount',
            ]);
        });
    }
};
