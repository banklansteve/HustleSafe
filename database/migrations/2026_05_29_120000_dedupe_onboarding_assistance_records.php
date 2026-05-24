<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_onboarding_assistance_records')) {
            return;
        }

        $duplicates = DB::table('staff_onboarding_assistance_records')
            ->select('user_id')
            ->whereNull('resolved_at')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        foreach ($duplicates as $userId) {
            $keep = DB::table('staff_onboarding_assistance_records')
                ->where('user_id', $userId)
                ->whereNull('resolved_at')
                ->orderByDesc('staleness_score')
                ->orderByDesc('updated_at')
                ->value('id');

            if ($keep) {
                DB::table('staff_onboarding_assistance_records')
                    ->where('user_id', $userId)
                    ->whereNull('resolved_at')
                    ->where('id', '!=', $keep)
                    ->delete();
            }
        }

        if (! Schema::hasIndex('staff_onboarding_assistance_records', 'staff_onboarding_user_id_idx')) {
            Schema::table('staff_onboarding_assistance_records', function (Blueprint $table): void {
                $table->index('user_id', 'staff_onboarding_user_id_idx');
            });
        }

        Schema::table('staff_onboarding_assistance_records', function (Blueprint $table): void {
            $table->dropUnique('staff_onboarding_user_scenario_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff_onboarding_assistance_records')) {
            return;
        }

        Schema::table('staff_onboarding_assistance_records', function (Blueprint $table): void {
            $table->unique(['user_id', 'scenario'], 'staff_onboarding_user_scenario_unique');
        });
    }
};
