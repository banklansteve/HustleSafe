<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quests')) {
            Schema::table('quests', function (Blueprint $table) {
                if (! Schema::hasColumn('quests', 'reference_code')) {
                    $table->string('reference_code', 24)->nullable()->unique();
                }
                if (! Schema::hasColumn('quests', 'start_timing')) {
                    $table->string('start_timing', 32)->default('flexible');
                }
                if (! Schema::hasColumn('quests', 'estimated_completion_days')) {
                    $table->unsignedSmallInteger('estimated_completion_days')->nullable();
                }
                if (! Schema::hasColumn('quests', 'site_visits_allowed')) {
                    $table->boolean('site_visits_allowed')->default(false);
                }
                if (! Schema::hasColumn('quests', 'scheduled_start_date')) {
                    $table->date('scheduled_start_date')->nullable();
                }
            });

            if (Schema::hasTable('local_governments') && ! Schema::hasColumn('quests', 'local_government_id')) {
                Schema::table('quests', function (Blueprint $table) {
                    $after = Schema::hasColumn('quests', 'state_id') ? 'state_id' : null;
                    $column = $table->foreignId('local_government_id')->nullable()->constrained('local_governments')->nullOnDelete();
                    if ($after !== null) {
                        $column->after($after);
                    }
                });
            }
        }

        if (! Schema::hasTable('quest_files')) {
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
        }

        if (! Schema::hasTable('quest_freelancer_invites')) {
            Schema::create('quest_freelancer_invites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
                $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['quest_id', 'freelancer_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_freelancer_invites');
        Schema::dropIfExists('quest_files');

        if (! Schema::hasTable('quests')) {
            return;
        }

        Schema::table('quests', function (Blueprint $table) {
            if (Schema::hasColumn('quests', 'local_government_id')) {
                $table->dropForeign(['local_government_id']);
            }
            $columns = array_filter([
                Schema::hasColumn('quests', 'reference_code') ? 'reference_code' : null,
                Schema::hasColumn('quests', 'local_government_id') ? 'local_government_id' : null,
                Schema::hasColumn('quests', 'start_timing') ? 'start_timing' : null,
                Schema::hasColumn('quests', 'estimated_completion_days') ? 'estimated_completion_days' : null,
                Schema::hasColumn('quests', 'site_visits_allowed') ? 'site_visits_allowed' : null,
                Schema::hasColumn('quests', 'scheduled_start_date') ? 'scheduled_start_date' : null,
            ]);
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
