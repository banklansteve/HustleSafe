<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quests')) {
            Schema::table('quests', function (Blueprint $table): void {
                if (! Schema::hasColumn('quests', 'escrow_funded_at')) {
                    $table->timestamp('escrow_funded_at')->nullable()->after('escrow_status')->index();
                }
                if (! Schema::hasColumn('quests', 'auto_completed_at')) {
                    $table->timestamp('auto_completed_at')->nullable()->after('completed_at')->index();
                }
            });

            DB::table('quests')
                ->where('escrow_status', 'funded')
                ->whereNull('escrow_funded_at')
                ->update(['escrow_funded_at' => DB::raw('COALESCE(updated_at, created_at)')]);
        }

        if (! Schema::hasTable('quest_lifecycle_email_logs')) {
            Schema::create('quest_lifecycle_email_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quest_id')->constrained('quests')->cascadeOnDelete();
                $table->string('email_key', 64);
                $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('sent_at')->useCurrent();
                $table->timestamps();
                $table->unique(['quest_id', 'email_key', 'recipient_user_id'], 'quest_lifecycle_email_unique');
            });
        }

        if (! Schema::hasTable('admin_activity_logs')) {
            Schema::create('admin_activity_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('action', 128);
                $table->string('subject_type', 128)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->json('properties')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
                $table->index(['subject_type', 'subject_id']);
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_logs');
        Schema::dropIfExists('quest_lifecycle_email_logs');

        if (Schema::hasTable('quests')) {
            Schema::table('quests', function (Blueprint $table): void {
                if (Schema::hasColumn('quests', 'auto_completed_at')) {
                    $table->dropColumn('auto_completed_at');
                }
                if (Schema::hasColumn('quests', 'escrow_funded_at')) {
                    $table->dropColumn('escrow_funded_at');
                }
            });
        }
    }
};
