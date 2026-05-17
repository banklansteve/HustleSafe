<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_saved_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('report_type', 80)->default('custom')->index();
            $table->json('builder_config')->nullable();
            $table->json('filters')->nullable();
            $table->string('date_preset', 40)->default('last_30_days');
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('schedule_frequency', 20)->nullable()->index();
            $table->json('schedule_recipients')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('admin_report_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_saved_report_id')->nullable()->constrained('admin_saved_reports')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_name');
            $table->string('report_type', 80);
            $table->string('format', 16);
            $table->string('status', 24)->default('pending')->index();
            $table->json('payload')->nullable();
            $table->string('disk', 32)->default('public');
            $table->string('path')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_report_exports');
        Schema::dropIfExists('admin_saved_reports');
    }
};
