<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freelancer_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            /** certification | professional_licence | insurance | qualification */
            $table->string('credential_type', 40)->index();
            $table->string('title');
            $table->string('issuing_authority')->nullable();
            $table->string('reference_number')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->text('coverage_summary')->nullable();
            $table->string('document_path')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'credential_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freelancer_credentials');
    }
};
