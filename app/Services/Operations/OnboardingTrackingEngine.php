<?php

namespace App\Services\Operations;

use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\QuestBookmark;
use App\Models\QuestOffer;
use App\Models\StaffOnboardingAssistanceRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class OnboardingTrackingEngine
{
    /** @var array<int, list<array<string, mixed>>> */
    private array $candidatesByUser = [];

    public function runDailyEvaluation(): int
    {
        $this->candidatesByUser = [];
        $this->evaluateClients();
        $this->evaluateFreelancers();
        $this->evaluateAbandonedDrafts();

        $count = 0;
        foreach ($this->candidatesByUser as $userId => $candidates) {
            $user = User::query()->find($userId);
            if ($user && $this->syncLatestAlertForUser($user, $candidates)) {
                $count++;
            }
        }

        return $count;
    }

    private function evaluateClients(): void
    {
        User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'client'))
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $this->evaluateClient($user);
                }
            });
    }

    private function evaluateClient(User $user): void
    {
        $publishedCount = Quest::query()
            ->where('client_id', $user->id)
            ->where('status', '!=', QuestStatus::Draft->value)
            ->count();

        if ($publishedCount === 0) {
            if ($user->created_at && $user->created_at->lte(now()->subDays(15))) {
                $this->queueCandidate($user, 'client', 'client_inactivity_no_quest', [
                    'milestone' => 'No published Quest (15-day cycle)',
                    'last_action' => $user->last_active_at ?? $user->created_at,
                    'metadata' => ['registered_at' => $user->created_at?->toIso8601String()],
                    'increment_cycle' => true,
                    'cycle_days' => 15,
                    'priority' => 80,
                ]);
            }

            if (! Quest::query()->where('client_id', $user->id)->exists() && $user->created_at?->lte(now()->subDays(15))) {
                $this->queueCandidate($user, 'client', 'client_never_quest_creation', [
                    'milestone' => 'Signed up — never started Quest creation',
                    'last_action' => $user->last_active_at ?? $user->created_at,
                    'metadata' => [],
                    'priority' => 75,
                ]);
            }
        } else {
            $this->resolveOpenAlertsForUser($user);
        }

        $stuckBudget = Quest::query()
            ->where('client_id', $user->id)
            ->where('status', QuestStatus::Draft->value)
            ->where(function ($q): void {
                $q->whereNull('budget_amount_minor')->orWhere('budget_amount_minor', '<=', 0);
            })
            ->where('updated_at', '<=', now()->subHours(72))
            ->latest('updated_at')
            ->first();

        if ($stuckBudget) {
            $this->queueCandidate($user, 'client', 'client_quest_stuck_before_budget', [
                'milestone' => 'Started Quest — did not reach budget step',
                'last_action' => $stuckBudget->updated_at,
                'fields' => ['title' => (bool) $stuckBudget->title, 'description' => (bool) $stuckBudget->description],
                'metadata' => ['quest_id' => $stuckBudget->id, 'quest_title' => $stuckBudget->title],
                'priority' => 90,
            ]);
        }

        $stuckPublish = Quest::query()
            ->where('client_id', $user->id)
            ->where('status', QuestStatus::Draft->value)
            ->where('budget_amount_minor', '>', 0)
            ->where('updated_at', '<=', now()->subHours(24))
            ->latest('updated_at')
            ->first();

        if ($stuckPublish) {
            $this->queueCandidate($user, 'client', 'client_quest_stuck_unpublished', [
                'milestone' => 'Reached final step — did not publish',
                'last_action' => $stuckPublish->updated_at,
                'fields' => ['budget' => true, 'title' => true],
                'metadata' => ['quest_id' => $stuckPublish->id, 'quest_title' => $stuckPublish->title],
                'priority' => 95,
            ]);
        }

        if ($publishedCount === 1) {
            $first = Quest::query()
                ->where('client_id', $user->id)
                ->where('status', '!=', QuestStatus::Draft->value)
                ->oldest()
                ->first();

            if ($first?->created_at?->lte(now()->subDays(45))) {
                $this->queueCandidate($user, 'client', 'client_retention_no_second_quest', [
                    'milestone' => 'Published first Quest — no second Quest (45 days)',
                    'last_action' => $first->created_at,
                    'metadata' => ['first_quest_id' => $first->id],
                    'priority' => 60,
                ]);
            }
        }
    }

    private function evaluateFreelancers(): void
    {
        User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'freelancer'))
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $this->evaluateFreelancer($user);
                }
            });
    }

    private function evaluateFreelancer(User $user): void
    {
        $submittedCount = QuestOffer::query()
            ->where('freelancer_id', $user->id)
            ->whereIn('status', ['submitted', 'shortlisted', 'accepted'])
            ->count();

        if ($submittedCount === 0) {
            if ($user->created_at?->lte(now()->subDays(15))) {
                $this->queueCandidate($user, 'freelancer', 'freelancer_inactivity_no_proposal', [
                    'milestone' => 'No proposal submitted (15-day cycle)',
                    'last_action' => $user->last_active_at ?? $user->created_at,
                    'increment_cycle' => true,
                    'cycle_days' => 15,
                    'priority' => 80,
                ]);
            }

            $bookmarks = QuestBookmark::query()->where('user_id', $user->id)->exists();
            $hasOffers = QuestOffer::query()->where('freelancer_id', $user->id)->exists();

            if (! $bookmarks && ! $hasOffers && $user->created_at?->lte(now()->subDays(15))) {
                $this->queueCandidate($user, 'freelancer', 'freelancer_never_viewed_quest', [
                    'milestone' => 'Signed up — never engaged with Quests',
                    'last_action' => $user->last_active_at ?? $user->created_at,
                    'metadata' => [],
                    'priority' => 70,
                ]);
            }

            if ($bookmarks && ! $hasOffers && $user->created_at?->lte(now()->subDays(5))) {
                $this->queueCandidate($user, 'freelancer', 'freelancer_viewed_no_proposal', [
                    'milestone' => 'Saved/viewed Quests — never submitted proposal',
                    'last_action' => $user->last_active_at ?? $user->updated_at,
                    'metadata' => [],
                    'priority' => 85,
                ]);
            }
        } else {
            $this->resolveOpenAlertsForUser($user);
        }

        $incomplete = QuestOffer::query()
            ->where('freelancer_id', $user->id)
            ->whereNotIn('status', ['submitted', 'shortlisted', 'accepted', 'declined', 'withdrawn'])
            ->where('updated_at', '<=', now()->subHours(48))
            ->where(function ($q): void {
                $q->whereNull('pitch')->orWhere('pitch', '');
            })
            ->latest('updated_at')
            ->first();

        if ($incomplete) {
            $this->queueCandidate($user, 'freelancer', 'freelancer_proposal_stuck_unsubmitted', [
                'milestone' => 'Started proposal — did not submit',
                'last_action' => $incomplete->updated_at,
                'metadata' => ['quest_id' => $incomplete->quest_id, 'offer_id' => $incomplete->id],
                'priority' => 92,
            ]);
        }

        if ($submittedCount === 1) {
            $first = QuestOffer::query()
                ->where('freelancer_id', $user->id)
                ->whereIn('status', ['submitted', 'shortlisted', 'accepted'])
                ->oldest()
                ->first();

            if ($first?->created_at?->lte(now()->subDays(30))) {
                $this->queueCandidate($user, 'freelancer', 'freelancer_retention_no_second_proposal', [
                    'milestone' => 'First proposal — no second (30 days)',
                    'last_action' => $first->created_at,
                    'metadata' => ['offer_id' => $first->id],
                    'priority' => 60,
                ]);
            }
        }
    }

    private function evaluateAbandonedDrafts(): void
    {
        Quest::query()
            ->with('client')
            ->where('status', QuestStatus::Draft->value)
            ->where('updated_at', '<=', now()->subHours(48))
            ->chunkById(50, function ($quests): void {
                foreach ($quests as $quest) {
                    if ($quest->client === null) {
                        continue;
                    }
                    $this->queueCandidate($quest->client, 'client', 'quest_draft_abandoned', [
                        'milestone' => 'Quest draft abandoned 48h+',
                        'last_action' => $quest->updated_at,
                        'fields' => [
                            'title' => (bool) $quest->title,
                            'budget' => (bool) $quest->budget_amount_minor,
                        ],
                        'metadata' => ['quest_id' => $quest->id, 'quest_title' => $quest->title],
                        'priority' => 88,
                    ]);
                }
            });

        QuestOffer::query()
            ->with('freelancer')
            ->where('updated_at', '<=', now()->subHours(48))
            ->where(function ($q): void {
                $q->whereNull('pitch')->orWhere('pitch', '');
            })
            ->whereNotIn('status', ['submitted', 'shortlisted', 'accepted'])
            ->chunkById(50, function ($offers): void {
                foreach ($offers as $offer) {
                    if ($offer->freelancer === null) {
                        continue;
                    }
                    $this->queueCandidate($offer->freelancer, 'freelancer', 'proposal_draft_abandoned', [
                        'milestone' => 'Proposal draft abandoned 48h+',
                        'last_action' => $offer->updated_at,
                        'metadata' => ['quest_id' => $offer->quest_id, 'offer_id' => $offer->id],
                        'priority' => 88,
                    ]);
                }
            });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function queueCandidate(User $user, string $userType, string $scenario, array $context): void
    {
        /** @var Carbon|null $lastAction */
        $lastAction = $context['last_action'] ?? $user->last_active_at ?? $user->updated_at;
        if (! $lastAction instanceof Carbon) {
            $lastAction = $lastAction ? Carbon::parse($lastAction) : now();
        }

        $staleness = $this->stalenessScore($user, $lastAction, $context['fields'] ?? [], 0);

        $this->candidatesByUser[$user->id][] = [
            'user_type' => $userType,
            'scenario' => $scenario,
            'milestone' => $context['milestone'] ?? Str::headline(str_replace('_', ' ', $scenario)),
            'last_action' => $lastAction,
            'fields' => $context['fields'] ?? [],
            'metadata' => $context['metadata'] ?? [],
            'increment_cycle' => $context['increment_cycle'] ?? false,
            'cycle_days' => $context['cycle_days'] ?? 15,
            'priority' => $context['priority'] ?? 50,
            'staleness_score' => $staleness,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     */
    private function syncLatestAlertForUser(User $user, array $candidates): bool
    {
        if ($candidates === []) {
            return false;
        }

        usort($candidates, function (array $a, array $b): int {
            if ($a['staleness_score'] !== $b['staleness_score']) {
                return $b['staleness_score'] <=> $a['staleness_score'];
            }

            return $b['priority'] <=> $a['priority'];
        });

        $winner = $candidates[0];
        $scenario = $winner['scenario'];

        $existing = StaffOnboardingAssistanceRecord::query()
            ->where('user_id', $user->id)
            ->whereNull('resolved_at')
            ->first();

        if ($existing?->status === 'resolved') {
            return false;
        }

        $sameScenarioOpen = $existing && $existing->scenario === $scenario;

        if ($sameScenarioOpen && ($winner['increment_cycle'] ?? false)) {
            $due = $existing->next_cycle_at ?? $existing->updated_at?->addDays($winner['cycle_days'] ?? 15);
            if ($due && $due->isFuture()) {
                return false;
            }
        }

        $cycles = 1;
        if ($sameScenarioOpen) {
            $cycles = ($winner['increment_cycle'] ?? false)
                ? $existing->cycles_elapsed + 1
                : $existing->cycles_elapsed;
        }

        $payload = [
            'user_type' => $winner['user_type'],
            'scenario' => $scenario,
            'milestone_reached' => $winner['milestone'],
            'status' => $existing?->status === 'contacted' ? 'contacted' : 'open',
            'staleness_score' => $winner['staleness_score'],
            'cycles_elapsed' => $cycles,
            'last_meaningful_action_at' => $winner['last_action'],
            'last_activity_at' => $user->last_active_at ?? $winner['last_action'],
            'fields_completed' => $winner['fields'],
            'flow_metadata' => $winner['metadata'],
            'return_sessions_count' => (int) ($existing?->return_sessions_count ?? 0),
            'next_cycle_at' => ($winner['increment_cycle'] ?? false)
                ? now()->addDays($winner['cycle_days'] ?? 15)
                : $existing?->next_cycle_at,
            'resolved_at' => null,
        ];

        if ($existing) {
            $existing->forceFill($payload)->save();
            $record = $existing;
        } else {
            $record = StaffOnboardingAssistanceRecord::query()->create([
                'user_id' => $user->id,
                ...$payload,
            ]);
        }

        StaffOnboardingAssistanceRecord::query()
            ->where('user_id', $user->id)
            ->whereNull('resolved_at')
            ->where('id', '!=', $record->id)
            ->delete();

        return true;
    }

    private function resolveOpenAlertsForUser(User $user): void
    {
        StaffOnboardingAssistanceRecord::query()
            ->where('user_id', $user->id)
            ->whereNull('resolved_at')
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);
    }

    /**
     * @param  array<string, bool>  $fieldsCompleted
     */
    private function stalenessScore(User $user, Carbon $lastAction, array $fieldsCompleted, int $returnSessions): int
    {
        $days = max(0, $lastAction->diffInDays(now()));
        $score = min(40, $days * 2);

        $completed = count(array_filter($fieldsCompleted));
        $total = max(1, count($fieldsCompleted) ?: 1);
        $progressRatio = $fieldsCompleted === [] ? 0 : ($completed / $total);
        $score += (int) round((1 - $progressRatio) * 25);

        if ($returnSessions <= 1) {
            $score += 10;
        }

        $tier = (int) ($user->kyc_tier ?? $user->verification_tier ?? 0);
        if ($tier < 2) {
            $score += 8;
        }

        $trust = (int) ($user->trust_score ?? $user->client_trust_score ?? 50);
        if ($trust < 40) {
            $score += 7;
        }

        if ($user->last_active_at && $user->last_active_at->gte(now()->subDays(3))) {
            $score = max(0, $score - 10);
        }

        return min(100, max(0, $score));
    }
}
