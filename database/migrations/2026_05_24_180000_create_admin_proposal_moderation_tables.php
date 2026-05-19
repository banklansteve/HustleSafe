<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quest_offers')) {
            return;
        }

        Schema::table('quest_offers', function (Blueprint $table): void {
            if (! Schema::hasColumn('quest_offers', 'admin_status')) {
                $table->string('admin_status', 32)->default('clear')->after('status')->index();
            }
            if (! Schema::hasColumn('quest_offers', 'admin_status_reason')) {
                $table->text('admin_status_reason')->nullable()->after('admin_status');
            }
            if (! Schema::hasColumn('quest_offers', 'admin_status_changed_by')) {
                $table->foreignId('admin_status_changed_by')->nullable()->after('admin_status_reason')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('quest_offers', 'admin_status_changed_at')) {
                $table->timestamp('admin_status_changed_at')->nullable()->after('admin_status_changed_by');
            }
            if (! Schema::hasColumn('quest_offers', 'admin_notice_severity')) {
                $table->string('admin_notice_severity', 32)->nullable()->after('admin_status_changed_at')->index();
            }
        });

        if (! Schema::hasTable('admin_proposal_flags')) {
            Schema::create('admin_proposal_flags', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quest_offer_id')->constrained('quest_offers')->cascadeOnDelete();
                $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_to_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('assigned_group', 64)->nullable();
                $table->string('type', 64)->index();
                $table->string('priority', 24)->default('medium')->index();
                $table->text('description');
                $table->string('visibility_impact', 64)->default('none');
                $table->date('due_at')->nullable();
                $table->string('status', 24)->default('open')->index();
                $table->string('resolution_outcome', 64)->nullable();
                $table->text('resolution_note')->nullable();
                $table->foreignId('resolved_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_proposal_notices')) {
            Schema::create('admin_proposal_notices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quest_offer_id')->constrained('quest_offers')->cascadeOnDelete();
                $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type', 32)->default('informational');
                $table->text('body');
                $table->boolean('visible_to_freelancer')->default(true)->index();
                $table->boolean('visible_to_client')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_proposal_notes')) {
            Schema::create('admin_proposal_notes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quest_offer_id')->constrained('quest_offers')->cascadeOnDelete();
                $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('admin_proposal_notes')->cascadeOnDelete();
                $table->text('body');
                $table->boolean('is_pinned')->default(false)->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_proposal_notes');
        Schema::dropIfExists('admin_proposal_notices');
        Schema::dropIfExists('admin_proposal_flags');

        if (! Schema::hasTable('quest_offers')) {
            return;
        }

        Schema::table('quest_offers', function (Blueprint $table): void {
            if (Schema::hasColumn('quest_offers', 'admin_status_changed_by')) {
                $table->dropConstrainedForeignId('admin_status_changed_by');
            }

            foreach ([
                'admin_notice_severity',
                'admin_status_changed_at',
                'admin_status_reason',
                'admin_status',
            ] as $column) {
                if (Schema::hasColumn('quest_offers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
