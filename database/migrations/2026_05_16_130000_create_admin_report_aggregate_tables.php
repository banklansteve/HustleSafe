<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_report_platform_daily_metrics', function (Blueprint $table): void {
            $table->id();
            $table->date('metric_date')->unique();
            $table->unsignedInteger('new_users')->default(0);
            $table->unsignedInteger('active_users')->default(0);
            $table->unsignedInteger('jobs_posted')->default(0);
            $table->unsignedInteger('jobs_completed')->default(0);
            $table->unsignedInteger('messages_sent')->default(0);
            $table->unsignedBigInteger('escrow_funded_minor')->default(0);
            $table->unsignedBigInteger('escrow_released_minor')->default(0);
            $table->timestamps();
        });

        Schema::create('admin_report_user_daily_metrics', function (Blueprint $table): void {
            $table->id();
            $table->date('metric_date')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('user_type', 32)->index();
            $table->unsignedBigInteger('category_id')->default(0)->index();
            $table->unsignedBigInteger('state_id')->nullable()->index();
            $table->unsignedBigInteger('local_government_id')->nullable()->index();
            $table->unsignedInteger('jobs_started')->default(0);
            $table->unsignedInteger('jobs_completed')->default(0);
            $table->unsignedInteger('jobs_disputed')->default(0);
            $table->unsignedInteger('proposals_sent')->default(0);
            $table->unsignedInteger('proposals_viewed')->default(0);
            $table->unsignedInteger('proposals_shortlisted')->default(0);
            $table->unsignedInteger('proposals_accepted')->default(0);
            $table->unsignedBigInteger('earnings_minor')->default(0);
            $table->unsignedBigInteger('spend_minor')->default(0);
            $table->decimal('rating_sum', 10, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamps();

            $table->unique(['metric_date', 'user_id', 'user_type', 'category_id'], 'admin_report_user_daily_unique');
        });

        Schema::create('admin_report_category_daily_metrics', function (Blueprint $table): void {
            $table->id();
            $table->date('metric_date')->index();
            $table->unsignedBigInteger('category_id')->default(0)->index();
            $table->unsignedBigInteger('state_id')->default(0)->index();
            $table->unsignedBigInteger('local_government_id')->default(0)->index();
            $table->unsignedInteger('jobs_posted')->default(0);
            $table->unsignedInteger('jobs_completed')->default(0);
            $table->unsignedInteger('hires')->default(0);
            $table->unsignedInteger('proposal_volume')->default(0);
            $table->unsignedInteger('freelancer_availability')->default(0);
            $table->unsignedBigInteger('budget_sum_minor')->default(0);
            $table->unsignedBigInteger('revenue_minor')->default(0);
            $table->unsignedInteger('disputes')->default(0);
            $table->timestamps();

            $table->unique(['metric_date', 'category_id', 'state_id', 'local_government_id'], 'admin_report_category_daily_unique');
        });

        Schema::create('admin_report_location_daily_metrics', function (Blueprint $table): void {
            $table->id();
            $table->date('metric_date')->index();
            $table->unsignedBigInteger('state_id')->default(0)->index();
            $table->unsignedBigInteger('local_government_id')->default(0)->index();
            $table->unsignedInteger('freelancers')->default(0);
            $table->unsignedInteger('clients')->default(0);
            $table->unsignedInteger('jobs_posted')->default(0);
            $table->unsignedInteger('jobs_completed')->default(0);
            $table->unsignedBigInteger('spend_minor')->default(0);
            $table->timestamps();

            $table->unique(['metric_date', 'state_id', 'local_government_id'], 'admin_report_location_daily_unique');
        });

        Schema::create('admin_report_revenue_daily_metrics', function (Blueprint $table): void {
            $table->id();
            $table->date('metric_date')->index();
            $table->string('fee_type', 32)->index();
            $table->unsignedBigInteger('category_id')->default(0)->index();
            $table->unsignedBigInteger('state_id')->default(0)->index();
            $table->string('user_segment', 32)->default('all')->index();
            $table->unsignedBigInteger('revenue_minor')->default(0);
            $table->timestamps();

            $table->unique(['metric_date', 'fee_type', 'category_id', 'state_id', 'user_segment'], 'admin_report_revenue_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_report_revenue_daily_metrics');
        Schema::dropIfExists('admin_report_location_daily_metrics');
        Schema::dropIfExists('admin_report_category_daily_metrics');
        Schema::dropIfExists('admin_report_user_daily_metrics');
        Schema::dropIfExists('admin_report_platform_daily_metrics');
    }
};
