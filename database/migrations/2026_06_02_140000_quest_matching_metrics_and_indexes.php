<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('freelancer_metrics')) {
            Schema::create('freelancer_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('location_state_id')->nullable()->constrained('states')->nullOnDelete();
                $table->foreignId('location_lga_id')->nullable()->constrained('local_governments')->nullOnDelete();
                $table->unsignedBigInteger('typical_job_value_minor')->nullable();
                $table->json('skills_list')->nullable();
                $table->decimal('completion_rate', 5, 2)->default(0);
                $table->decimal('average_rating', 4, 2)->nullable();
                $table->unsignedTinyInteger('verification_level')->default(0);
                $table->timestamp('last_proposal_at')->nullable();
                $table->unsignedSmallInteger('dispute_count_last_6_months')->default(0);
                $table->unsignedSmallInteger('cancellation_count_last_6_months')->default(0);
                $table->unsignedSmallInteger('quick_turnaround_completed_count')->default(0);
                $table->json('niche_completions_by_category')->nullable();
                $table->timestamp('refreshed_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('quests', function (Blueprint $table) {
            if (! Schema::hasColumn('quests', 'required_skills')) {
                $table->json('required_skills')->nullable()->after('description');
            }
            if (! Schema::hasColumn('quests', 'required_languages')) {
                $table->json('required_languages')->nullable()->after('required_skills');
            }
        });

        if (! $this->indexExists('quests', 'quests_matching_category_location_status_idx')) {
            Schema::table('quests', function (Blueprint $table) {
                $table->index(
                    ['quest_category_id', 'local_government_id', 'state_id', 'status'],
                    'quests_matching_category_location_status_idx'
                );
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('quests', 'quests_matching_category_location_status_idx')) {
            Schema::table('quests', function (Blueprint $table) {
                $table->dropIndex('quests_matching_category_location_status_idx');
            });
        }

        Schema::table('quests', function (Blueprint $table) {
            if (Schema::hasColumn('quests', 'required_languages')) {
                $table->dropColumn('required_languages');
            }
            if (Schema::hasColumn('quests', 'required_skills')) {
                $table->dropColumn('required_skills');
            }
        });

        Schema::dropIfExists('freelancer_metrics');
    }

    protected function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $rows = $connection->select("PRAGMA index_list('{$table}')");

            return collect($rows)->contains(fn ($row) => ($row->name ?? '') === $index);
        }

        $database = $connection->getDatabaseName();

        return (bool) $connection->selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $index],
        );
    }
};
