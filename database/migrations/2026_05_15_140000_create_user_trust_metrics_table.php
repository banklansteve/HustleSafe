<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_trust_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('freelancer_trust_score')->default(0);
            $table->unsignedSmallInteger('client_trust_score')->default(50);
            $table->unsignedTinyInteger('profile_completion_percent')->default(0);
            $table->decimal('avg_rating_as_freelancer', 3, 2)->nullable();
            $table->decimal('avg_rating_as_client', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count_as_freelancer')->default(0);
            $table->unsignedInteger('ratings_count_as_client')->default(0);
            $table->timestamp('last_recomputed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_trust_metrics');
    }
};
