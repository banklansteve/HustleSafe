<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quest_categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('quest_categories', 'icon_name')) {
                $table->string('icon_name', 80)->default('briefcase')->after('description');
            }
            if (! Schema::hasColumn('quest_categories', 'icon_color')) {
                $table->string('icon_color', 32)->default('#0f766e')->after('icon_name');
            }
            if (! Schema::hasColumn('quest_categories', 'status')) {
                $table->string('status', 24)->default('active')->index()->after('sort_order');
            }
            if (! Schema::hasColumn('quest_categories', 'previous_status')) {
                $table->string('previous_status', 24)->nullable()->after('status');
            }
            if (! Schema::hasColumn('quest_categories', 'uses_fee_override')) {
                $table->boolean('uses_fee_override')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('quest_categories', 'client_fee_percent')) {
                $table->decimal('client_fee_percent', 5, 2)->nullable()->after('uses_fee_override');
            }
            if (! Schema::hasColumn('quest_categories', 'freelancer_fee_percent')) {
                $table->decimal('freelancer_fee_percent', 5, 2)->nullable()->after('client_fee_percent');
            }
            if (! Schema::hasColumn('quest_categories', 'budget_guardrails_enabled')) {
                $table->boolean('budget_guardrails_enabled')->default(false)->after('freelancer_fee_percent');
            }
            if (! Schema::hasColumn('quest_categories', 'min_budget_minor')) {
                $table->unsignedBigInteger('min_budget_minor')->nullable()->after('budget_guardrails_enabled');
            }
            if (! Schema::hasColumn('quest_categories', 'max_budget_minor')) {
                $table->unsignedBigInteger('max_budget_minor')->nullable()->after('min_budget_minor');
            }
            if (! Schema::hasColumn('quest_categories', 'high_value_approval_enabled')) {
                $table->boolean('high_value_approval_enabled')->default(false)->after('max_budget_minor');
            }
            if (! Schema::hasColumn('quest_categories', 'high_value_threshold_minor')) {
                $table->unsignedBigInteger('high_value_threshold_minor')->nullable()->after('high_value_approval_enabled');
            }
            if (! Schema::hasColumn('quest_categories', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('high_value_threshold_minor');
            }
            if (! Schema::hasColumn('quest_categories', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('quest_categories', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
        });

        DB::table('quest_categories')->where('is_active', true)->update(['status' => 'active']);
        DB::table('quest_categories')->where('is_active', false)->update(['status' => 'hidden']);

        try {
            Schema::table('quest_categories', function (Blueprint $table): void {
                $table->dropUnique('quest_categories_slug_unique');
            });
        } catch (Throwable) {
            // Existing environments may already have removed or renamed this index.
        }

        Schema::table('quest_categories', function (Blueprint $table): void {
            $table->index(['parent_id', 'sort_order'], 'quest_categories_parent_sort_idx');
            $table->index(['parent_id', 'slug'], 'quest_categories_parent_slug_idx');
        });
    }

    public function down(): void
    {
        Schema::table('quest_categories', function (Blueprint $table): void {
            $table->dropIndex('quest_categories_parent_sort_idx');
            $table->dropIndex('quest_categories_parent_slug_idx');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'archived_at',
                'high_value_threshold_minor',
                'high_value_approval_enabled',
                'max_budget_minor',
                'min_budget_minor',
                'budget_guardrails_enabled',
                'freelancer_fee_percent',
                'client_fee_percent',
                'uses_fee_override',
                'previous_status',
                'status',
                'icon_color',
                'icon_name',
            ]);
        });

        Schema::table('quest_categories', function (Blueprint $table): void {
            $table->unique('slug');
        });
    }
};
