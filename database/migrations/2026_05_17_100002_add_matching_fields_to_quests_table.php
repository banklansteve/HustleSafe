<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->foreignId('quest_category_id')->nullable()->after('description')->constrained('quest_categories')->nullOnDelete();
            $table->foreignId('state_id')->nullable()->after('quest_category_id')->constrained('states')->nullOnDelete();
            $table->string('city', 160)->nullable()->after('state_id');
            $table->decimal('latitude', 10, 7)->nullable()->after('city');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->dropForeign(['quest_category_id']);
            $table->dropForeign(['state_id']);
            $table->dropColumn(['quest_category_id', 'state_id', 'city', 'latitude', 'longitude']);
        });
    }
};
