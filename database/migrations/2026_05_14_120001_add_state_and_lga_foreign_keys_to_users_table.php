<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('state_id')->nullable()->after('address_line')->constrained('states')->nullOnDelete();
            $table->foreignId('local_government_id')->nullable()->after('state_id')->constrained('local_governments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['state_id']);
            $table->dropForeign(['local_government_id']);
            $table->dropColumn(['state_id', 'local_government_id']);
        });
    }
};
