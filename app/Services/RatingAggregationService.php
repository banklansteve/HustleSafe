<?php

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Enums\ReviewType;
use App\Models\User;
use App\Models\UserTrustMetric;
use Illuminate\Support\Facades\DB;

class RatingAggregationService
{
    public function syncForUser(User $user): void
    {
        $freelancer = $this->averageForParty($user->id, 'freelancer');
        $client = $this->averageForParty($user->id, 'client');

        UserTrustMetric::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'avg_rating_as_freelancer' => $freelancer['avg'],
                'ratings_count_as_freelancer' => $freelancer['count'],
                'avg_rating_as_client' => $client['avg'],
                'ratings_count_as_client' => $client['count'],
            ]
        );
    }

    /**
     * @return array{avg: ?float, count: int}
     */
    protected function averageForParty(int $userId, string $party): array
    {
        $questColumn = $party === 'freelancer' ? 'quests.freelancer_id' : 'quests.client_id';

        $briefWeight = (float) config('review_moderation.quality.brief_rating_weight', 0.4);

        $row = DB::table('reviews')
            ->join('quests', 'quests.id', '=', 'reviews.quest_id')
            ->where($questColumn, $userId)
            ->where('reviews.reviewee_id', $userId)
            ->where('reviews.review_type', ReviewType::Full->value)
            ->whereNotNull('reviews.rating')
            ->where('reviews.status', ReviewStatus::Published->value)
            ->selectRaw(
                'SUM(CASE WHEN reviews.is_brief = 1 THEN reviews.rating * ? ELSE reviews.rating END) /
                NULLIF(SUM(CASE WHEN reviews.is_brief = 1 THEN ? ELSE 1 END), 0) as avg_rating,
                COUNT(*) as cnt',
                [$briefWeight, $briefWeight],
            )
            ->first();

        $avg = $row && $row->avg_rating !== null ? round((float) $row->avg_rating, 2) : null;
        $count = (int) ($row->cnt ?? 0);

        return ['avg' => $avg, 'count' => $count];
    }
}
