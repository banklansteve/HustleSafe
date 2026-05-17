<?php

namespace App\Console\Commands;

use App\Models\PromotionBadge;
use App\Models\User;
use Illuminate\Console\Command;

class RefreshPromotionBadgesCommand extends Command
{
    protected $signature = 'promotions:refresh-badges';

    protected $description = 'Refresh automatically awarded promotion badges.';

    public function handle(): int
    {
        $badges = PromotionBadge::query()->where('is_automatic', true)->where('status', 'active')->get()->keyBy('slug');

        User::query()->with(['role', 'trustMetrics'])->chunkById(200, function ($users) use ($badges): void {
            foreach ($users as $user) {
                if (($user->kyc_tier ?? $user->verification_tier ?? 0) >= 4 && isset($badges['verified-pro'])) {
                    $this->award($badges['verified-pro'], $user, 'Automatic KYC tier award.');
                }
                if (($user->kyc_tier ?? $user->verification_tier ?? 0) >= 5 && isset($badges['verified-business'])) {
                    $this->award($badges['verified-business'], $user, 'Automatic business verification award.');
                }
                if ($user->created_at?->lte(now()->subYear()) && (($user->trust_score ?? $user->client_trust_score ?? 0) >= 70) && isset($badges['long-term-partner'])) {
                    $this->award($badges['long-term-partner'], $user, 'Automatic loyalty badge.');
                }
            }
        });

        $this->info('Promotion badges refreshed.');

        return self::SUCCESS;
    }

    private function award(PromotionBadge $badge, User $user, string $reason): void
    {
        $badge->users()->syncWithoutDetaching([
            $user->id => [
                'justification' => $reason,
                'awarded_at' => now(),
                'revoked_at' => null,
            ],
        ]);
    }
}
