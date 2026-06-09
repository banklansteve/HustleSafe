<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quest_patrol_investigations', function (Blueprint $table) {
            $table->id();
            $table->string('case_reference', 32)->unique();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->string('status', 32)->default('open');
            $table->foreignId('opened_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('severity', 16)->default('medium');
            $table->json('timeline')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'status'], 'qpi_subject_status_idx');
            $table->index(['status', 'created_at'], 'qpi_status_created_idx');
        });

        Schema::create('proposal_reference_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('quest_category_id')->nullable();
            $table->longText('body');
            $table->unsignedBigInteger('source_proposal_id')->nullable();
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 24)->default('published');
            $table->unsignedTinyInteger('quality_rating')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'prt_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_reference_templates');
        Schema::dropIfExists('quest_patrol_investigations');
    }
};
