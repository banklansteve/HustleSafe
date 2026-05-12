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

        $row = DB::table('reviews')
            ->join('quests', 'quests.id', '=', 'reviews.quest_id')
            ->where($questColumn, $userId)
            ->where('reviews.reviewee_id', $userId)
            ->where('reviews.review_type', ReviewType::Full->value)
            ->whereNotNull('reviews.rating')
            ->where('reviews.status', ReviewStatus::Published->value)
            ->selectRaw('AVG(reviews.rating) as avg_rating, COUNT(*) as cnt')
            ->first();

        $avg = $row && $row->avg_rating !== null ? round((float) $row->avg_rating, 2) : null;
        $count = (int) ($row->cnt ?? 0);

        return ['avg' => $avg, 'count' => $count];
    }
}
