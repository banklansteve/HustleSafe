<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_broadcast_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('category')->index();
            $table->string('suggested_audience')->nullable();
            $table->string('subject');
            $table->string('preview_text')->nullable();
            $table->longText('body_html');
            $table->boolean('is_system')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('email_broadcasts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->nullable()->constrained('email_broadcast_templates')->nullOnDelete();
            $table->foreignId('created_by_admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject');
            $table->string('preview_text')->nullable();
            $table->string('reply_to')->nullable();
            $table->string('from_name')->nullable();
            $table->longText('body_html');
            $table->json('audience')->nullable();
            $table->string('audience_description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('queued_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('opened_count')->default(0);
            $table->unsignedInteger('clicked_count')->default(0);
            $table->unsignedInteger('bounced_count')->default(0);
            $table->unsignedInteger('unsubscribed_count')->default(0);
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('email_broadcast_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('email_broadcast_id')->constrained('email_broadcasts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('status')->default('queued')->index();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamps();
            $table->unique(['email_broadcast_id', 'user_id'], 'email_broadcast_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_broadcast_recipients');
        Schema::dropIfExists('email_broadcasts');
        Schema::dropIfExists('email_broadcast_templates');
    }
};
