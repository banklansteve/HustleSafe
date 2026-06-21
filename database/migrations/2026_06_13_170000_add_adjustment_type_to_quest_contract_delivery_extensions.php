<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quest_contract_delivery_extensions', function (Blueprint $table) {
            $table->string('adjustment_type', 32)->default('extension')->after('extension_number');
        });
    }

    public function down(): void
    {
        Schema::table('quest_contract_delivery_extensions', function (Blueprint $table) {
            $table->dropColumn('adjustment_type');
        });
    }
};
