<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_account_merges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('primary_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('secondary_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason_code', 64)->nullable();
            $table->text('reason_notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('merged_at');
            $table->timestamps();

            $table->unique('secondary_user_id');
            $table->index(['primary_user_id', 'merged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_account_merges');
    }
};
