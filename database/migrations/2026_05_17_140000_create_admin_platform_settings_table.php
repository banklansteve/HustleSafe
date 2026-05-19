<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('section', 80)->index();
            $table->string('key', 120)->unique();
            $table->json('value')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_platform_settings');
    }
};
