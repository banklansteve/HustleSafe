<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role_group', 32);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 24)->default('active');
            $table->text('reason');
            $table->foreignId('assigned_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revoked_reason')->nullable();
            $table->timestamps();

            $table->index(['staff_user_id', 'status']);
            $table->index(['role_group', 'status']);
        });

        Schema::create('staff_leave_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('annual_days')->default(0);
            $table->unsignedSmallInteger('sick_days')->default(0);
            $table->unsignedSmallInteger('emergency_days')->default(0);
            $table->unsignedSmallInteger('unpaid_days')->default(0);
            $table->unsignedSmallInteger('annual_days_used')->default(0);
            $table->unsignedSmallInteger('sick_days_used')->default(0);
            $table->unsignedSmallInteger('emergency_days_used')->default(0);
            $table->unsignedSmallInteger('unpaid_days_used')->default(0);
            $table->timestamps();

            $table->unique(['staff_user_id', 'year']);
        });

        Schema::create('staff_leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('leave_type', 24);
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days_requested')->default(1);
            $table->text('reason')->nullable();
            $table->string('status', 24)->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['staff_user_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('staff_session_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('login_at');
            $table->timestamp('logout_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('active_seconds')->default(0);
            $table->unsignedInteger('idle_seconds')->default(0);
            $table->unsignedSmallInteger('actions_count')->default(0);
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 2000)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['staff_user_id', 'login_at']);
        });

        Schema::create('staff_page_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('section_key', 120);
            $table->unsignedInteger('seconds_spent')->default(0);
            $table->unsignedInteger('visits')->default(1);
            $table->date('activity_date');
            $table->timestamps();

            $table->unique(['staff_user_id', 'section_key', 'activity_date'], 'staff_page_activity_unique');
        });

        Schema::create('staff_action_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action_type', 100);
            $table->string('entity_type', 160)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('outcome', 120)->nullable();
            $table->timestamp('acted_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['staff_user_id', 'acted_at']);
            $table->index(['action_type', 'acted_at']);
        });

        Schema::create('staff_activity_benchmarks', function (Blueprint $table): void {
            $table->id();
            $table->string('role_group', 32);
            $table->unsignedSmallInteger('minimum_weekly_actions')->default(0);
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('role_group');
        });

        Schema::create('staff_performance_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('score', 5, 2)->default(0);
            $table->json('metric_counts')->nullable();
            $table->unsignedInteger('volume_points')->default(0);
            $table->unsignedInteger('resolution_points')->default(0);
            $table->unsignedInteger('speed_points')->default(0);
            $table->boolean('overridden')->default(false);
            $table->decimal('overridden_score', 5, 2)->nullable();
            $table->text('override_note')->nullable();
            $table->foreignId('override_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('override_at')->nullable();
            $table->timestamps();

            $table->unique(['staff_user_id', 'year', 'month']);
        });

        Schema::create('staff_payroll_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->string('payment_frequency', 20)->default('monthly');
            $table->text('bank_details_encrypted')->nullable();
            $table->timestamps();

            $table->unique('staff_user_id');
        });

        Schema::create('staff_payroll_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20);
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->date('effective_date');
            $table->boolean('is_recurring')->default(false);
            $table->string('reference', 190)->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['staff_user_id', 'effective_date']);
        });

        Schema::create('staff_payslips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('gross_pay', 15, 2);
            $table->decimal('bonuses', 15, 2)->default(0);
            $table->decimal('deductions', 15, 2)->default(0);
            $table->decimal('net_pay', 15, 2);
            $table->string('pdf_path')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->unique(['staff_user_id', 'year', 'month']);
        });

        Schema::create('staff_hr_compliance_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('severity', 24);
            $table->string('status', 24)->default('open');
            $table->text('incident_note');
            $table->json('evidence')->nullable();
            $table->foreignId('opened_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_hr_suspicious_activity_flags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('staff_session_log_id')->nullable()->constrained('staff_session_logs')->nullOnDelete();
            $table->text('pattern');
            $table->text('note')->nullable();
            $table->foreignId('flagged_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('flagged_at');
            $table->timestamps();
        });

        Schema::create('staff_hr_audit_trails', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action_type', 120);
            $table->foreignId('target_staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 2000)->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('staff_hr_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('alert_type', 120);
            $table->string('severity', 24)->default('medium');
            $table->text('message');
            $table->json('payload')->nullable();
            $table->timestamp('triggered_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['alert_type', 'triggered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_hr_alerts');
        Schema::dropIfExists('staff_hr_audit_trails');
        Schema::dropIfExists('staff_hr_suspicious_activity_flags');
        Schema::dropIfExists('staff_hr_compliance_cases');
        Schema::dropIfExists('staff_payslips');
        Schema::dropIfExists('staff_payroll_adjustments');
        Schema::dropIfExists('staff_payroll_profiles');
        Schema::dropIfExists('staff_performance_scores');
        Schema::dropIfExists('staff_activity_benchmarks');
        Schema::dropIfExists('staff_action_logs');
        Schema::dropIfExists('staff_page_activity_logs');
        Schema::dropIfExists('staff_session_logs');
        Schema::dropIfExists('staff_leave_requests');
        Schema::dropIfExists('staff_leave_balances');
        Schema::dropIfExists('staff_role_assignments');
    }
};
