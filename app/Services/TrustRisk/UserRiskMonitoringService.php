<?php

namespace App\Services\TrustRisk;

use App\Models\User;
use App\Models\UserRiskProfile;
use App\Services\Operations\StaffTrustWatchlistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class UserRiskMonitoringService
{
    public function __construct(
        private readonly UserRiskScoreCalculator $calculator,
        private readonly TrustRiskSettingsService $settings,
        private readonly StaffTrustWatchlistService $watchlist,
    ) {}

    public function recalculateAndPersist(User $user): UserRiskProfile
    {
        $result = $this->calculator->calculate($user);
        $thresholds = $this->settings->thresholds();
        $tier = $this->settings->tierForScore($result['composite']);
        $inQueue = $result['composite'] >= $thresholds['monitoring_queue_min_score'];

        $profile = UserRiskProfile::query()->firstOrNew(['user_id' => $user->id]);
        $previousScore = (int) ($profile->composite_score ?? 0);
        $previousTier = (string) ($profile->tier ?? 'low');

        $profile->fill([
            'composite_score' => $result['composite'],
            'tier' => $tier,
            'breakdown' => $result['breakdown'],
            'signals' => $result['signals'],
            'in_risk_queue' => $inQueue,
            'queued_at' => $inQueue ? ($profile->queued_at ?? now()) : null,
            'calculated_at' => now(),
            'previous_score' => $profile->exists ? $previousScore : null,
        ]);
        $profile->save();

        $this->watchlist->handleRiskScoreChange($user, $previousScore, $result['composite'], $previousTier, $tier);

        return $profile;
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>, filters: array<string, mixed>}
     */
    public function riskQueue(Request $request): array
    {
        $tier = (string) $request->query('tier', '');
        $q = (string) $request->query('q', '');
        $perPage = max(10, min(100, (int) $request->query('per_page', 25)));
        $page = max(1, (int) $request->query('page', 1));

        $query = UserRiskProfile::query()
            ->with('user:id,name,email,username,created_at')
            ->where('in_risk_queue', true)
            ->orderByDesc('composite_score');

        if ($tier !== '') {
            $query->where('tier', $tier);
        }

        if ($q !== '') {
            $query->whereHas('user', fn ($u) => $u
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('username', 'like', "%{$q}%"));
        }

        $total = (clone $query)->count();
        $items = $query->forPage($page, $perPage)->get()->map(function (UserRiskProfile $p) {
            $row = $this->profileRow($p);
            $subject = $p->user ?? User::query()->find($p->user_id);
            $row['on_watchlist'] = $subject
                ? ($this->watchlist->userOnWatchlistSummary($subject)['on_watchlist'] ?? false)
                : false;

            return $row;
        });

        return [
            'items' => $items->all(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) max(1, ceil($total / $perPage)),
            ],
            'filters' => ['tier' => $tier, 'q' => $q],
            'thresholds' => $this->settings->thresholds(),
        ];
    }

    public function userDetail(User $user): array
    {
        $profile = UserRiskProfile::query()->where('user_id', $user->id)->first();
        if ($profile === null) {
            $profile = $this->recalculateAndPersist($user);
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'profile' => $this->profileRow($profile),
            'thresholds' => $this->settings->thresholds(),
            'on_watchlist' => $this->watchlist->userOnWatchlistSummary($user),
        ];
    }

    public function profileRow(UserRiskProfile $profile): array
    {
        $user = $profile->user;

        return [
            'user_id' => $profile->user_id,
            'name' => $user?->name,
            'email' => $user?->email,
            'username' => $user?->username,
            'composite_score' => (int) $profile->composite_score,
            'tier' => $profile->tier,
            'breakdown' => $profile->breakdown ?? [],
            'in_risk_queue' => (bool) $profile->in_risk_queue,
            'queued_at' => $profile->queued_at?->toIso8601String(),
            'calculated_at' => $profile->calculated_at?->toIso8601String(),
            'previous_score' => $profile->previous_score,
        ];
    }

    public function queueCount(): int
    {
        if (! Schema::hasTable('user_risk_profiles')) {
            return 0;
        }

        return UserRiskProfile::query()->where('in_risk_queue', true)->count();
    }

}
