<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff_role_assignments')) {
            Schema::table('staff_role_assignments', function (Blueprint $table): void {
                $table->string('role_group', 64)->change();
            });
        }

        if (Schema::hasTable('staff_activity_benchmarks')) {
            Schema::table('staff_activity_benchmarks', function (Blueprint $table): void {
                $table->string('role_group', 64)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('staff_role_assignments')) {
            Schema::table('staff_role_assignments', function (Blueprint $table): void {
                $table->string('role_group', 32)->change();
            });
        }

        if (Schema::hasTable('staff_activity_benchmarks')) {
            Schema::table('staff_activity_benchmarks', function (Blueprint $table): void {
                $table->string('role_group', 32)->change();
            });
        }
    }
};
