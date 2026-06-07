<?php

use App\Models\KycSetting;
use App\Models\User;
use App\Services\Verification\VerificationEngineService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kyc_settings')) {
            return;
        }

        $this->extendFreelancerLimits();
        $this->syncFreelancerLevelRequirements();
        $this->recalculateFreelancerLevels();
    }

    private function extendFreelancerLimits(): void
    {
        $limits = KycSetting::value('verification_limits');
        if (! is_array($limits)) {
            return;
        }

        $map = $limits['freelancer_proposal_minor'] ?? [];
        if (! is_array($map) || isset($map[6]) || isset($map['6'])) {
            return;
        }

        $defaults = config('verification_engine.limits.freelancer_proposal_minor', []);
        $map[6] = (int) ($map[5] ?? $map['5'] ?? $defaults[6] ?? 1_000_000_000);
        $map[5] = (int) ($map[4] ?? $map['4'] ?? $defaults[5] ?? 500_000_000);
        $map[4] = (int) ($map[3] ?? $map['3'] ?? $defaults[4] ?? 200_000_000);
        $map[3] = (int) ($defaults[3] ?? 100_000_000);

        $limits['freelancer_proposal_minor'] = $map;

        KycSetting::query()->updateOrCreate(
            ['key' => 'verification_limits'],
            ['value' => $limits],
        );
    }

    private function syncFreelancerLevelRequirements(): void
    {
        $stored = KycSetting::value('verification_freelancer_level_requirements');
        $config = config('verification_engine.freelancer_levels', []);
        $storedMax = is_array($stored) ? max(array_map('intval', array_keys($stored))) : 0;

        if ($storedMax >= 6) {
            return;
        }

        KycSetting::query()->updateOrCreate(
            ['key' => 'verification_freelancer_level_requirements'],
            ['value' => $config],
        );

        $stage = KycSetting::value('verification_stage_content');
        if (! is_array($stage) || ! isset($stage['freelancer'][6])) {
            KycSetting::query()->updateOrCreate(
                ['key' => 'verification_stage_content'],
                ['value' => array_replace_recursive(
                    config('verification_engine.stage_content', []),
                    is_array($stage) ? $stage : [],
                )],
            );
        }
    }

    private function recalculateFreelancerLevels(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $engine = app(VerificationEngineService::class);

        User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['freelancer', 'seller', 'provider']))
            ->where(function ($q): void {
                $q->where('current_verification_level', '>=', 3)
                    ->orWhere('kyc_tier', '>=', 3)
                    ->orWhere('verification_tier', '>=', 3);
            })
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($engine): void {
                foreach ($users as $user) {
                    $engine->recalculate($user, null, 'Freelancer ladder extended to L6.');
                }
            });
    }

    public function down(): void
    {
        // Intentionally left empty.
    }
};
