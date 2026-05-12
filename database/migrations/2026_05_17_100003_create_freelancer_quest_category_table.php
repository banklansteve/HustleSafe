<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freelancer_quest_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quest_category_id')->constrained('quest_categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'quest_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freelancer_quest_category');
    }
};
