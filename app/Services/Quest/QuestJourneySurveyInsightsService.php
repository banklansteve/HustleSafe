<?php

namespace App\Services\Quest;

use App\Models\QuestJourneySurvey;
use Illuminate\Support\Carbon;

class QuestJourneySurveyInsightsService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboardPanel(): array
    {
        return [
            'summary' => $this->summary(),
            'nps_trend' => $this->npsTrend(),
            'averages_by_question' => $this->averageScoresByQuestion(),
            'by_category' => $this->scoresByCategory(),
            'dual_low_quests' => $this->dualLowQuests(),
            'recent_free_text' => $this->recentFreeText(limit: 8),
            'rejected_cohort_summary' => $this->rejectedCohortSummary(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $base = QuestJourneySurvey::query()->whereNotNull('submitted_at');

        return [
            'total_submitted' => (clone $base)->count(),
            'client_completed' => (clone $base)->where('cohort', 'client_completed')->count(),
            'freelancer_awarded' => (clone $base)->where('cohort', 'freelancer_awarded')->count(),
            'freelancer_rejected' => (clone $base)->where('cohort', 'freelancer_rejected')->count(),
            'emails_sent' => QuestJourneySurvey::query()->whereNotNull('email_sent_at')->count(),
            'started_not_finished' => QuestJourneySurvey::query()
                ->whereNotNull('first_answer_at')
                ->whereNull('submitted_at')
                ->where('expires_at', '>', now())
                ->count(),
        ];
    }

    /**
     * @return list<array{month: string, nps: float|null, responses: int}>
     */
    public function npsTrend(int $months = 6): array
    {
        $since = now()->subMonths($months)->startOfMonth();
        $rows = QuestJourneySurvey::query()
            ->where('cohort', 'freelancer_awarded')
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', $since)
            ->get(['answers', 'submitted_at']);

        $buckets = [];
        foreach ($rows as $row) {
            $nps = $row->answers['apply_again_nps'] ?? null;
            if (! is_numeric($nps)) {
                continue;
            }
            $month = $row->submitted_at?->format('Y-m') ?? 'unknown';
            $buckets[$month]['scores'][] = (int) $nps;
        }

        $out = [];
        for ($i = $months; $i >= 0; $i--) {
            $month = now()->subMonths($i)->format('Y-m');
            $scores = $buckets[$month]['scores'] ?? [];
            $out[] = [
                'month' => $month,
                'nps' => $this->calculateNps($scores),
                'responses' => count($scores),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{question_key: string, cohort: string, average: float|null, responses: int}>
     */
    public function averageScoresByQuestion(): array
    {
        $submitted = QuestJourneySurvey::query()
            ->whereNotNull('submitted_at')
            ->get(['cohort', 'answers']);

        $accumulators = [];

        foreach ($submitted as $survey) {
            $answers = $survey->answers ?? [];
            foreach ($answers as $key => $value) {
                $score = $this->numericScore($survey->cohort, $key, $value);
                if ($score === null) {
                    continue;
                }
                $bucketKey = "{$survey->cohort}:{$key}";
                $accumulators[$bucketKey]['cohort'] = $survey->cohort;
                $accumulators[$bucketKey]['question_key'] = $key;
                $accumulators[$bucketKey]['scores'][] = $score;
            }
        }

        return collect($accumulators)
            ->map(fn (array $row) => [
                'question_key' => $row['question_key'],
                'cohort' => $row['cohort'],
                'average' => round(array_sum($row['scores']) / max(count($row['scores']), 1), 2),
                'responses' => count($row['scores']),
            ])
            ->sortByDesc('responses')
            ->values()
            ->take(24)
            ->all();
    }

    /**
     * @return list<array{category: string, client_avg: float|null, awarded_avg: float|null, rejected_avg: float|null}>
     */
    public function scoresByCategory(): array
    {
        $rows = QuestJourneySurvey::query()
            ->whereNotNull('submitted_at')
            ->with('quest.questCategory:id,name')
            ->get();

        $buckets = [];

        foreach ($rows as $survey) {
            $category = $survey->quest?->questCategory?->name ?? 'Uncategorised';
            $primaryScore = $this->primaryScoreForCohort($survey);
            if ($primaryScore === null) {
                continue;
            }
            $buckets[$category][$survey->cohort][] = $primaryScore;
        }

        return collect($buckets)
            ->map(function (array $cohorts, string $category) {
                return [
                    'category' => $category,
                    'client_avg' => $this->avg($cohorts['client_completed'] ?? []),
                    'awarded_avg' => $this->avg($cohorts['freelancer_awarded'] ?? []),
                    'rejected_avg' => $this->avg($cohorts['freelancer_rejected'] ?? []),
                ];
            })
            ->sortByDesc(fn (array $row) => ($row['client_avg'] ?? 0) + ($row['awarded_avg'] ?? 0))
            ->values()
            ->take(12)
            ->all();
    }

    /**
     * @return list<array{quest_id: int, quest_title: string, client_score: float|null, freelancer_score: float|null}>
     */
    public function dualLowQuests(int $limit = 10): array
    {
        $clientSurveys = QuestJourneySurvey::query()
            ->where('cohort', 'client_completed')
            ->whereNotNull('submitted_at')
            ->with('quest:id,title')
            ->get();

        $awardedSurveys = QuestJourneySurvey::query()
            ->where('cohort', 'freelancer_awarded')
            ->whereNotNull('submitted_at')
            ->get()
            ->keyBy('quest_id');

        $flagged = [];

        foreach ($clientSurveys as $client) {
            $clientScore = $this->primaryScoreForCohort($client);
            $awarded = $awardedSurveys->get($client->quest_id);
            if (! $awarded) {
                continue;
            }
            $freelancerScore = $this->primaryScoreForCohort($awarded);
            if ($clientScore === null || $freelancerScore === null) {
                continue;
            }
            if ($clientScore <= 2.5 && $freelancerScore <= 2.5) {
                $flagged[] = [
                    'quest_id' => $client->quest_id,
                    'quest_title' => $client->quest?->title ?? "Quest #{$client->quest_id}",
                    'client_score' => $clientScore,
                    'freelancer_score' => $freelancerScore,
                ];
            }
        }

        return array_slice($flagged, 0, $limit);
    }

    /**
     * @return list<array{id: int, cohort: string, quest_title: string, question_key: string, text: string, submitted_at: string|null}>
     */
    public function recentFreeText(int $limit = 20, ?string $search = null): array
    {
        $textKeys = ['one_improvement', 'win_more_help', 'frustrating_parts'];

        $query = QuestJourneySurvey::query()
            ->whereNotNull('submitted_at')
            ->with('quest:id,title')
            ->latest('submitted_at');

        $rows = $query->limit(200)->get();
        $out = [];

        foreach ($rows as $survey) {
            foreach ($textKeys as $key) {
                $text = trim((string) ($survey->answers[$key] ?? ''));
                if ($text === '') {
                    continue;
                }
                if ($search && ! str_contains(strtolower($text), strtolower($search))) {
                    continue;
                }
                $out[] = [
                    'id' => $survey->id,
                    'cohort' => $survey->cohort,
                    'quest_title' => $survey->quest?->title ?? '',
                    'question_key' => $key,
                    'text' => $text,
                    'submitted_at' => $survey->submitted_at?->toIso8601String(),
                ];
            }
        }

        return array_slice($out, 0, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    public function rejectedCohortSummary(): array
    {
        $rows = QuestJourneySurvey::query()
            ->where('cohort', 'freelancer_rejected')
            ->whereNotNull('submitted_at')
            ->get(['answers']);

        $briefScores = [];
        $fairScores = [];

        foreach ($rows as $row) {
            $brief = $this->numericScore('freelancer_rejected', 'brief_adequacy', $row->answers['brief_adequacy'] ?? null);
            if ($brief !== null) {
                $briefScores[] = $brief;
            }
            $fair = $this->numericScore('freelancer_rejected', 'platform_fairness', $row->answers['platform_fairness'] ?? null);
            if ($fair !== null) {
                $fairScores[] = $fair;
            }
        }

        return [
            'responses' => $rows->count(),
            'avg_brief_adequacy' => $this->avg($briefScores),
            'avg_platform_fairness' => $this->avg($fairScores),
        ];
    }

    /**
     * @return array{data: \Illuminate\Contracts\Pagination\LengthAwarePaginator, filters: array<string, mixed>}
     */
    public function userFeedbackListing(?int $userId = null, ?string $cohort = null, ?string $search = null): array
    {
        $query = QuestJourneySurvey::query()
            ->with([
                'user:id,name,email',
                'quest:id,title,quest_category_id',
                'quest.questCategory:id,name',
            ])
            ->whereNotNull('submitted_at')
            ->latest('submitted_at');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($cohort) {
            $query->where('cohort', $cohort);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('quest', fn ($quest) => $quest->where('title', 'like', "%{$search}%"));
            });
        }

        return [
            'data' => $query->paginate(25)->through(fn (QuestJourneySurvey $survey) => [
                'id' => $survey->id,
                'cohort' => $survey->cohort,
                'submitted_at' => $survey->submitted_at?->toIso8601String(),
                'user' => [
                    'id' => $survey->user_id,
                    'name' => $survey->user?->name,
                    'email' => $survey->user?->email,
                ],
                'quest' => [
                    'id' => $survey->quest_id,
                    'title' => $survey->quest?->title,
                    'category' => $survey->quest?->questCategory?->name,
                ],
                'answers' => $survey->answers,
                'primary_score' => $this->primaryScoreForCohort($survey),
            ]),
            'filters' => [
                'user_id' => $userId,
                'cohort' => $cohort,
                'search' => $search,
            ],
        ];
    }

    private function primaryScoreForCohort(QuestJourneySurvey $survey): ?float
    {
        $answers = $survey->answers ?? [];
        $key = match ($survey->cohort) {
            'client_completed' => 'proposal_quality',
            'freelancer_awarded' => 'payment_release_smooth',
            'freelancer_rejected' => 'brief_adequacy',
            default => null,
        };

        if (! $key) {
            return null;
        }

        $score = $this->numericScore($survey->cohort, $key, $answers[$key] ?? null);

        return $score !== null ? (float) $score : null;
    }

    private function numericScore(string $cohort, string $key, mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($key === 'apply_again_nps' && is_numeric($value)) {
            return (float) $value;
        }

        $mapped = config("quest_journey_survey.score_maps.{$key}.{$value}");
        if ($mapped !== null) {
            return (float) $mapped;
        }

        $optionSets = config('quest_journey_survey.option_sets', []);
        foreach ($optionSets as $options) {
            $values = collect($options)->pluck('value')->values()->all();
            $index = array_search($value, $values, true);
            if ($index !== false) {
                return (float) (count($values) - $index);
            }
        }

        return null;
    }

    /**
     * @param  list<int|float>  $scores
     */
    private function calculateNps(array $scores): ?float
    {
        if ($scores === []) {
            return null;
        }

        $promoters = count(array_filter($scores, fn ($s) => $s >= 9));
        $detractors = count(array_filter($scores, fn ($s) => $s <= 6));
        $total = count($scores);

        return round((($promoters - $detractors) / $total) * 100, 1);
    }

    /**
     * @param  list<int|float>  $scores
     */
    private function avg(array $scores): ?float
    {
        if ($scores === []) {
            return null;
        }

        return round(array_sum($scores) / count($scores), 2);
    }
}
