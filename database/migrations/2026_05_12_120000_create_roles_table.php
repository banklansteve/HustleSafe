<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('roles')->insert([
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Full platform access.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Client',
                'slug' => 'client',
                'description' => 'Posts missions, funds escrow, approves delivery.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Freelancer',
                'slug' => 'freelancer',
                'description' => 'Creates offers, delivers work, receives payouts.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
