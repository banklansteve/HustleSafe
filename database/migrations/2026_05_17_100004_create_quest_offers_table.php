<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quest_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('submitted')->index();
            $table->text('pitch')->nullable();
            $table->unsignedBigInteger('quoted_amount_minor')->nullable();
            $table->timestamps();

            $table->unique(['quest_id', 'freelancer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_offers');
    }
};
