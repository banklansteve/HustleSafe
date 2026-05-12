<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freelancer_business_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('cac_registration_number')->nullable();
            /** not_submitted | pending | verified | rejected | manual_review */
            $table->string('cac_verification_status', 32)->default('not_submitted')->index();
            $table->timestamp('cac_verified_at')->nullable();
            $table->timestamp('cac_last_checked_at')->nullable();
            $table->text('cac_verification_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freelancer_business_profiles');
    }
};
