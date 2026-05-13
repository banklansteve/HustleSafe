<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->string('reference_code', 24)->nullable()->unique()->after('uuid');
            $table->foreignId('local_government_id')->nullable()->after('state_id')->constrained('local_governments')->nullOnDelete();
            $table->string('start_timing', 32)->default('flexible')->after('status');
            $table->unsignedSmallInteger('estimated_completion_days')->nullable()->after('start_timing');
            $table->boolean('site_visits_allowed')->default(false)->after('estimated_completion_days');
            $table->date('scheduled_start_date')->nullable()->after('site_visits_allowed');
        });

        Schema::create('quest_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 160)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('quest_freelancer_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['quest_id', 'freelancer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_freelancer_invites');
        Schema::dropIfExists('quest_files');

        Schema::table('quests', function (Blueprint $table) {
            $table->dropForeign(['local_government_id']);
            $table->dropColumn([
                'reference_code',
                'local_government_id',
                'start_timing',
                'estimated_completion_days',
                'site_visits_allowed',
                'scheduled_start_date',
            ]);
        });
    }
};
