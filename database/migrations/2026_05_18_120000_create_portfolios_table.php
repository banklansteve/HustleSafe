<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quest_id')->nullable()->constrained('quests')->nullOnDelete();
            $table->foreignId('category_id')->constrained('quest_categories')->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('quest_categories')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('slug', 160)->unique();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('project_cost_minor')->nullable();
            $table->string('cover_path')->nullable();
            /** draft | published */
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('admin_hidden')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('favorites_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolios');
    }
};
