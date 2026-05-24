<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff_onboarding_assistance_records')) {
            return;
        }

        Schema::create('staff_onboarding_assistance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('user_type', 16)->index();
            $table->string('scenario', 80)->index();
            $table->string('milestone_reached', 120)->nullable();
            $table->string('status', 24)->default('open')->index();
            $table->unsignedSmallInteger('staleness_score')->default(0)->index();
            $table->unsignedSmallInteger('cycles_elapsed')->default(1);
            $table->timestamp('last_meaningful_action_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->json('fields_completed')->nullable();
            $table->json('flow_metadata')->nullable();
            $table->unsignedSmallInteger('return_sessions_count')->default(0);
            $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('next_cycle_at')->nullable();
            $table->timestamps();

            $table->index('user_id', 'staff_onboarding_user_id_idx');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_onboarding_assistance_records');
    }
};
