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

            if (! Schema::hasColumn('quest_offers', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('quest_offers', 'declined_at')) {
                $table->timestamp('declined_at')->nullable()->after('accepted_at');
            }
            if (! Schema::hasColumn('quest_offers', 'withdrawn_at')) {
                $table->timestamp('withdrawn_at')->nullable()->after('declined_at');
            }
            if (! Schema::hasColumn('quest_offers', 'shortlisted_at')) {
                $table->timestamp('shortlisted_at')->nullable()->after('withdrawn_at');
            }
            if (! Schema::hasColumn('quest_offers', 'client_pinned_at')) {
                $table->timestamp('client_pinned_at')->nullable()->after('shortlisted_at');
            }
            if (! Schema::hasColumn('quest_offers', 'client_view_count')) {
                $table->unsignedInteger('client_view_count')->default(0)->after('client_pinned_at');
            }
            if (! Schema::hasColumn('quest_offers', 'last_client_view_at')) {
                $table->timestamp('last_client_view_at')->nullable()->after('client_view_count');
            }
            if (! Schema::hasColumn('quest_offers', 'freelancer_edit_deadline_at')) {
                $table->timestamp('freelancer_edit_deadline_at')->nullable()->after('last_client_view_at');
            }
        });

        if (Schema::hasTable('quest_offers')
            && ! Schema::hasIndex('quest_offers', 'quest_offers_quest_freelancer_status_idx')) {
            Schema::table('quest_offers', function (Blueprint $table): void {
                $table->index(['quest_id', 'freelancer_id', 'status'], 'quest_offers_quest_freelancer_status_idx');
            });
        }

        Schema::table('quests', function (Blueprint $table): void {
            if (! Schema::hasColumn('quests', 'escrow_status')) {
                $table->string('escrow_status', 32)->default('none')->after('status')->index();
            }
            if (! Schema::hasColumn('quests', 'accepted_quest_offer_id')) {
                $table->foreignId('accepted_quest_offer_id')->nullable()->after('escrow_status')->constrained('quest_offers')->nullOnDelete();
            }
        });

        Schema::table('content_reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('content_reports', 'severity')) {
                $table->string('severity', 24)->default('standard')->after('reason')->index();
            }
            if (! Schema::hasColumn('content_reports', 'intake_channel')) {
                $table->string('intake_channel', 32)->default('in_app')->after('severity');
            }
            if (! Schema::hasColumn('content_reports', 'evidence_url')) {
                $table->string('evidence_url', 512)->nullable()->after('details');
            }
        });
    }

    public function down(): void
    {
        Schema::table('content_reports', function (Blueprint $table): void {
            if (Schema::hasColumn('content_reports', 'evidence_url')) {
                $table->dropColumn('evidence_url');
            }
            if (Schema::hasColumn('content_reports', 'intake_channel')) {
                $table->dropColumn('intake_channel');
            }
            if (Schema::hasColumn('content_reports', 'severity')) {
                $table->dropColumn('severity');
            }
        });

        Schema::table('quests', function (Blueprint $table): void {
            if (Schema::hasColumn('quests', 'accepted_quest_offer_id')) {
                $table->dropConstrainedForeignId('accepted_quest_offer_id');
            }
            if (Schema::hasColumn('quests', 'escrow_status')) {
                $table->dropColumn('escrow_status');
            }
        });

        Schema::table('quest_offers', function (Blueprint $table): void {
            if (Schema::hasColumn('quest_offers', 'freelancer_edit_deadline_at')) {
                $table->dropColumn('freelancer_edit_deadline_at');
            }
            if (Schema::hasColumn('quest_offers', 'last_client_view_at')) {
                $table->dropColumn('last_client_view_at');
            }
            if (Schema::hasColumn('quest_offers', 'client_view_count')) {
                $table->dropColumn('client_view_count');
            }
            if (Schema::hasColumn('quest_offers', 'client_pinned_at')) {
                $table->dropColumn('client_pinned_at');
            }
            if (Schema::hasColumn('quest_offers', 'shortlisted_at')) {
                $table->dropColumn('shortlisted_at');
            }
            if (Schema::hasColumn('quest_offers', 'withdrawn_at')) {
                $table->dropColumn('withdrawn_at');
            }
            if (Schema::hasColumn('quest_offers', 'declined_at')) {
                $table->dropColumn('declined_at');
            }
            if (Schema::hasColumn('quest_offers', 'accepted_at')) {
                $table->dropColumn('accepted_at');
            }

            if (Schema::hasIndex('quest_offers', 'quest_offers_quest_freelancer_status_idx')) {
                $table->dropIndex('quest_offers_quest_freelancer_status_idx');
            }

            if (! Schema::hasIndex('quest_offers', ['quest_id', 'freelancer_id'], 'unique')) {
                $table->unique(['quest_id', 'freelancer_id']);
            }
        });
    }
};
