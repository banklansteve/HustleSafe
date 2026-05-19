<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_quest_flags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quest_id')->constrained('quests')->cascadeOnDelete();
            $table->foreignId('created_by_admin_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assigned_group', 80)->nullable()->index();
            $table->string('type', 80)->index();
            $table->string('priority', 24)->default('medium')->index();
            $table->text('description');
            $table->date('due_at')->nullable()->index();
            $table->string('status', 24)->default('open')->index();
            $table->string('resolution_outcome', 80)->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['quest_id', 'status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_quest_flags');
    }
};
