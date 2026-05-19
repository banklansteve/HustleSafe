<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table): void {
            if (! Schema::hasColumn('quests', 'admin_status')) {
                $table->string('admin_status', 32)->default('clear')->after('status')->index();
            }
            if (! Schema::hasColumn('quests', 'admin_status_reason')) {
                $table->text('admin_status_reason')->nullable()->after('admin_status');
            }
            if (! Schema::hasColumn('quests', 'admin_status_changed_by')) {
                $table->foreignId('admin_status_changed_by')->nullable()->after('admin_status_reason')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('quests', 'admin_status_changed_at')) {
                $table->timestamp('admin_status_changed_at')->nullable()->after('admin_status_changed_by');
            }
        });

        if (! Schema::hasTable('admin_quest_notices')) {
            Schema::create('admin_quest_notices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type', 32)->default('informational');
                $table->text('body');
                $table->boolean('visible_to_users')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_quest_notes')) {
            Schema::create('admin_quest_notes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
                $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('admin_quest_notes')->cascadeOnDelete();
                $table->text('body');
                $table->boolean('is_pinned')->default(false)->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_quest_notes');
        Schema::dropIfExists('admin_quest_notices');

        Schema::table('quests', function (Blueprint $table): void {
            if (Schema::hasColumn('quests', 'admin_status_changed_by')) {
                $table->dropConstrainedForeignId('admin_status_changed_by');
            }
            foreach (['admin_status_changed_at', 'admin_status_reason', 'admin_status'] as $column) {
                if (Schema::hasColumn('quests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
