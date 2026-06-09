<?php

namespace App\Services\Admin\QuestPatrol;

use App\Enums\QuestPatrolFlagStatus;
use App\Enums\QuestPatrolFlagType;
use App\Enums\QuestPatrolSubjectType;
use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\QuestBoost;
use App\Models\QuestOffer;
use App\Models\QuestPatrolFlag;
use App\Models\User;
use App\Services\Verification\VerificationEngineService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class QuestPatrolAnomalyService
{
    /** @var array<int, array{median: int, sample: int}>|null */
    private ?array $categoryBands = null;

    public function flagsAvailable(): bool
    {
        return Schema::hasTable('quest_patrol_flags');
    }

    public function scanAll(): int
    {
        if (! $this->flagsAvailable()) {
            return 0;
        }

        $created = 0;
        Quest::query()
            ->whereIn('status', [QuestStatus::Open->value, QuestStatus::Assigned->value, QuestStatus::InProgress->value])
            ->with(['client', 'questCategory'])
            ->orderByDesc('id')
            ->limit(500)
            ->each(function (Quest $quest) use (&$created): void {
                $created += $this->scanQuest($quest);
            });

        QuestOffer::query()
            ->where('created_at', '>=', now()->subDays(14))
            ->with(['freelancer', 'quest.questCategory'])
            ->orderByDesc('id')
            ->limit(500)
            ->each(function (QuestOffer $proposal) use (&$created): void {
                $created += $this->scanProposal($proposal);
            });

        return $created;
    }

    public function scanQuest(Quest $quest): int
    {
        if (! $this->flagsAvailable()) {
            return 0;
        }

        $quest->loadMissing(['client', 'questCategory']);
        $created = 0;
        $created += $this->flagBudgetAnomalies($quest) ? 1 : 0;
        $created += $this->flagTierMismatch($quest) ? 1 : 0;
        $created += $this->flagDuplicateQuest($quest) ? 1 : 0;
        $created += $this->flagCategoryShift($quest) ? 1 : 0;
        $created += $this->flagInstantCompletion($quest) ? 1 : 0;
        $created += $this->flagBoostPatterns($quest) ? 1 : 0;

        return $created;
    }

    public function scanProposal(QuestOffer $proposal): int
    {
        if (! $this->flagsAvailable()) {
            return 0;
        }

        $proposal->loadMissing(['freelancer', 'quest']);
        $created = 0;
        $created += $this->flagProposalPriceMismatch($proposal) ? 1 : 0;
        $created += $this->flagVelocitySpike($proposal) ? 1 : 0;
        $created += $this->flagWinRateAnomaly($proposal) ? 1 : 0;

        return $created;
    }

    /**
     * @param  list<int>  $questIds
     * @return array<int, array<string, mixed>>
     */
    public function questSummaries(array $questIds): array
    {
        if (! $this->flagsAvailable() || $questIds === []) {
            return [];
        }

        return QuestPatrolFlag::query()
            ->where('subject_type', QuestPatrolSubjectType::Quest->value)
            ->where('status', QuestPatrolFlagStatus::Open->value)
            ->whereIn('subject_id', $questIds)
            ->orderByRaw("FIELD(severity, 'high', 'medium', 'low')")
            ->get()
            ->groupBy('subject_id')
            ->map(fn (Collection $flags) => $this->summaryFromFlags($flags))
            ->all();
    }

    /**
     * @param  list<int>  $proposalIds
     * @return array<int, array<string, mixed>>
     */
    public function proposalSummaries(array $proposalIds): array
    {
        if (! $this->flagsAvailable() || $proposalIds === []) {
            return [];
        }

        return QuestPatrolFlag::query()
            ->where('subject_type', QuestPatrolSubjectType::Proposal->value)
            ->where('status', QuestPatrolFlagStatus::Open->value)
            ->whereIn('subject_id', $proposalIds)
            ->orderByRaw("FIELD(severity, 'high', 'medium', 'low')")
            ->get()
            ->groupBy('subject_id')
            ->map(fn (Collection $flags) => $this->summaryFromFlags($flags))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openFlagsForQuest(int $questId): array
    {
        if (! $this->flagsAvailable()) {
            return [];
        }

        return QuestPatrolFlag::query()
            ->where('subject_type', QuestPatrolSubjectType::Quest->value)
            ->where('subject_id', $questId)
            ->where('status', QuestPatrolFlagStatus::Open->value)
            ->orderByDesc('detected_at')
            ->get()
            ->map(fn (QuestPatrolFlag $flag) => $this->flagPayload($flag))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openFlagsForProposal(int $proposalId): array
    {
        if (! $this->flagsAvailable()) {
            return [];
        }

        return QuestPatrolFlag::query()
            ->where('subject_type', QuestPatrolSubjectType::Proposal->value)
            ->where('subject_id', $proposalId)
            ->where('status', QuestPatrolFlagStatus::Open->value)
            ->orderByDesc('detected_at')
            ->get()
            ->map(fn (QuestPatrolFlag $flag) => $this->flagPayload($flag))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function collusionReport(Quest $quest): array
    {
        $quest->loadMissing(['client', 'freelancer']);
        $clientId = $quest->client_id;
        $since = now()->subDays(14);

        $awards = Quest::query()
            ->where('client_id', $clientId)
            ->whereNotNull('freelancer_id')
            ->where('updated_at', '>=', $since)
            ->with('freelancer:id,name,username')
            ->get();

        $byFreelancer = $awards->groupBy('freelancer_id')->map(fn (Collection $rows, $freelancerId) => [
            'freelancer_id' => (int) $freelancerId,
            'freelancer_name' => $rows->first()?->freelancer?->name,
            'count' => $rows->count(),
            'avg_value_minor' => (int) round($rows->avg('budget_amount_minor') ?? 0),
            'fast_completions' => $rows->filter(fn (Quest $q) => $q->created_at && $q->updated_at && $q->created_at->diffInHours($q->updated_at) <= 48)->count(),
        ])->sortByDesc('count')->values();

        $risk = 'low';
        $headline = 'No strong collusion signals detected.';
        $top = $byFreelancer->first();
        if ($top && $top['count'] >= 3 && $top['fast_completions'] >= 2) {
            $risk = 'high';
            $headline = "Client awarded {$top['count']} quests to the same freelancer in 2 weeks with {$top['fast_completions']} fast completions.";
        } elseif ($top && $top['count'] >= 3) {
            $risk = 'medium';
            $headline = "Client awarded {$top['count']} quests to the same freelancer recently.";
        }

        return [
            'risk' => $risk,
            'headline' => $headline,
            'freelancer_breakdown' => $byFreelancer->take(5)->all(),
            'total_awards_14d' => $awards->count(),
        ];
    }

    private function flagBudgetAnomalies(Quest $quest): bool
    {
        $budget = (int) $quest->budget_amount_minor;
        if ($budget <= 0) {
            return false;
        }

        $deviation = $this->budgetDeviationPercent($quest);
        if ($deviation === null) {
            return false;
        }

        $threshold = (int) config('quest_patrol.budget_deviation_percent', 50);
        if ($deviation > $threshold) {
            return $this->upsertFlag(
                QuestPatrolSubjectType::Quest,
                $quest->id,
                QuestPatrolFlagType::BudgetAnomalyHigh,
                "quest:budget_high:{$quest->id}",
                [
                    'budget_minor' => $budget,
                    'deviation_percent' => $deviation,
                    'market_median_minor' => $this->categoryBands()[$quest->quest_category_id]['median'] ?? null,
                ],
                $deviation >= 100 ? 'high' : 'medium',
            );
        }

        if ($deviation < -$threshold) {
            return $this->upsertFlag(
                QuestPatrolSubjectType::Quest,
                $quest->id,
                QuestPatrolFlagType::BudgetAnomalyLow,
                "quest:budget_low:{$quest->id}",
                ['budget_minor' => $budget, 'deviation_percent' => $deviation],
                'medium',
            );
        }

        return false;
    }

    private function flagTierMismatch(Quest $quest): bool
    {
        $client = $quest->client;
        if (! $client) {
            return false;
        }

        $limit = app(VerificationEngineService::class)->clientPostingLimitMinor($client);
        $budget = (int) $quest->budget_amount_minor;
        if ($limit <= 0 || $budget <= $limit) {
            return false;
        }

        return $this->upsertFlag(
            QuestPatrolSubjectType::Quest,
            $quest->id,
            QuestPatrolFlagType::TierMismatch,
            "quest:tier_mismatch:{$quest->id}",
            [
                'budget_minor' => $budget,
                'tier_limit_minor' => $limit,
                'client_tier' => $client->verification_tier,
            ],
            'high',
        );
    }

    private function flagDuplicateQuest(Quest $quest): bool
    {
        $hours = (int) config('quest_patrol.duplicate_quest_window_hours', 72);
        $duplicates = Quest::query()
            ->where('client_id', $quest->client_id)
            ->where('id', '!=', $quest->id)
            ->where('created_at', '>=', $quest->created_at?->copy()->subHours($hours))
            ->where('title', $quest->title)
            ->count();

        if ($duplicates < 1) {
            return false;
        }

        return $this->upsertFlag(
            QuestPatrolSubjectType::Quest,
            $quest->id,
            QuestPatrolFlagType::DuplicateQuest,
            "quest:duplicate:{$quest->id}",
            ['duplicate_count' => $duplicates],
            'medium',
        );
    }

    private function flagCategoryShift(Quest $quest): bool
    {
        $client = $quest->client;
        if (! $client || ! $quest->quest_category_id) {
            return false;
        }

        $priorCategoryIds = Quest::query()
            ->where('client_id', $client->id)
            ->where('id', '!=', $quest->id)
            ->whereNotNull('quest_category_id')
            ->orderByDesc('id')
            ->limit(5)
            ->pluck('quest_category_id')
            ->unique()
            ->values();

        if ($priorCategoryIds->isEmpty()) {
            $days = (int) config('quest_patrol.new_account_days', 14);
            if ($client->created_at->diffInDays(now()) <= $days && (int) $quest->budget_amount_minor >= 500_000_00) {
                return $this->upsertFlag(
                    QuestPatrolSubjectType::Quest,
                    $quest->id,
                    QuestPatrolFlagType::NewAccountUnfamiliarCategory,
                    "quest:new_account_category:{$quest->id}",
                    ['category_id' => $quest->quest_category_id],
                    'medium',
                );
            }

            return false;
        }

        if ($priorCategoryIds->contains($quest->quest_category_id)) {
            return false;
        }

        return $this->upsertFlag(
            QuestPatrolSubjectType::Quest,
            $quest->id,
            QuestPatrolFlagType::CategoryShift,
            "quest:category_shift:{$quest->id}",
            ['category_id' => $quest->quest_category_id, 'prior_categories' => $priorCategoryIds->all()],
            'medium',
        );
    }

    private function flagInstantCompletion(Quest $quest): bool
    {
        $status = $quest->status instanceof QuestStatus ? $quest->status->value : (string) $quest->status;
        if (! in_array($status, [QuestStatus::Completed->value, QuestStatus::Closed->value], true)) {
            return false;
        }

        $acceptedAt = $quest->accepted_at ?? $quest->escrow_funded_at ?? $quest->updated_at;
        $completedAt = $quest->completed_at ?? $quest->updated_at;
        if (! $acceptedAt || ! $completedAt) {
            return false;
        }

        $hours = (float) config('quest_patrol.instant_completion_hours', 1);
        if ($acceptedAt->diffInMinutes($completedAt) > ($hours * 60)) {
            return false;
        }

        return $this->upsertFlag(
            QuestPatrolSubjectType::Quest,
            $quest->id,
            QuestPatrolFlagType::InstantCompletion,
            "quest:instant_completion:{$quest->id}",
            ['minutes' => $acceptedAt->diffInMinutes($completedAt)],
            'high',
        );
    }

    private function flagBoostPatterns(Quest $quest): bool
    {
        $created = false;
        $clientId = $quest->client_id;
        if (! $clientId) {
            return false;
        }

        $windowHours = (int) config('quest_patrol.boost_spam_window_hours', 48);
        $threshold = (int) config('quest_patrol.boost_spam_threshold', 3);
        $recentBoosts = QuestBoost::query()
            ->where('client_id', $clientId)
            ->where('granted_at', '>=', now()->subHours($windowHours))
            ->count();

        if ($recentBoosts >= $threshold) {
            $created = $this->upsertFlag(
                QuestPatrolSubjectType::Quest,
                $quest->id,
                QuestPatrolFlagType::BoostSpam,
                "quest:boost_spam:{$clientId}:{$windowHours}",
                ['boost_count' => $recentBoosts],
                'medium',
            ) || $created;
        }

        $duplicateBoosts = QuestBoost::query()
            ->where('quest_id', $quest->id)
            ->where('granted_at', '>=', now()->subDays(30))
            ->count();
        if ($duplicateBoosts >= 2) {
            $created = $this->upsertFlag(
                QuestPatrolSubjectType::Quest,
                $quest->id,
                QuestPatrolFlagType::DuplicateBoost,
                "quest:duplicate_boost:{$quest->id}",
                ['boost_count' => $duplicateBoosts],
                'medium',
            ) || $created;
        }

        return $created;
    }

    private function flagProposalPriceMismatch(QuestOffer $proposal): bool
    {
        $quest = $proposal->quest;
        $budget = (int) ($quest?->budget_amount_minor ?? 0);
        $quoted = (int) ($proposal->quoted_amount_minor ?? 0);
        if ($budget <= 0 || $quoted <= 0) {
            return false;
        }

        $ratio = ($quoted / $budget) * 100;
        if ($ratio <= 200) {
            return false;
        }

        return $this->upsertFlag(
            QuestPatrolSubjectType::Proposal,
            $proposal->id,
            QuestPatrolFlagType::PriceMismatch,
            "proposal:price_mismatch:{$proposal->id}",
            ['quoted_minor' => $quoted, 'budget_minor' => $budget, 'ratio_percent' => round($ratio, 1)],
            $ratio >= 300 ? 'high' : 'medium',
        );
    }

    private function flagVelocitySpike(QuestOffer $proposal): bool
    {
        $freelancerId = $proposal->freelancer_id;
        if (! $freelancerId) {
            return false;
        }

        $hours = (int) config('quest_patrol.proposal_velocity_window_hours', 2);
        $threshold = (int) config('quest_patrol.proposal_velocity_threshold', 20);
        $count = QuestOffer::query()
            ->where('freelancer_id', $freelancerId)
            ->where('created_at', '>=', now()->subHours($hours))
            ->count();

        if ($count < $threshold) {
            return false;
        }

        return $this->upsertFlag(
            QuestPatrolSubjectType::Proposal,
            $proposal->id,
            QuestPatrolFlagType::VelocitySpike,
            "proposal:velocity:{$freelancerId}:{$hours}",
            ['proposal_count' => $count],
            'medium',
        );
    }

    private function flagWinRateAnomaly(QuestOffer $proposal): bool
    {
        $freelancerId = $proposal->freelancer_id;
        if (! $freelancerId) {
            return false;
        }

        $days = (int) config('quest_patrol.win_rate_window_days', 7);
        $threshold = (int) config('quest_patrol.win_rate_threshold_percent', 80);
        $since = now()->subDays($days);

        $submitted = QuestOffer::query()
            ->where('freelancer_id', $freelancerId)
            ->where('created_at', '>=', $since)
            ->count();
        $awarded = QuestOffer::query()
            ->where('freelancer_id', $freelancerId)
            ->where('created_at', '>=', $since)
            ->where('status', 'accepted')
            ->count();

        if ($submitted < 5) {
            return false;
        }

        $rate = ($awarded / max(1, $submitted)) * 100;
        if ($rate < $threshold) {
            return false;
        }

        return $this->upsertFlag(
            QuestPatrolSubjectType::Proposal,
            $proposal->id,
            QuestPatrolFlagType::WinRateAnomaly,
            "proposal:win_rate:{$freelancerId}:{$days}",
            ['win_rate_percent' => round($rate, 1), 'submitted' => $submitted, 'awarded' => $awarded],
            'high',
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function upsertFlag(
        QuestPatrolSubjectType $subjectType,
        int $subjectId,
        QuestPatrolFlagType $type,
        string $fingerprint,
        array $meta = [],
        ?string $severity = null,
    ): bool {
        $existing = QuestPatrolFlag::query()->where('fingerprint', $fingerprint)->first();
        if ($existing && $existing->status !== QuestPatrolFlagStatus::Open->value) {
            return false;
        }

        if ($existing) {
            $existing->forceFill([
                'meta' => array_merge($existing->meta ?? [], $meta),
                'detected_at' => now(),
            ])->save();

            return false;
        }

        $flag = QuestPatrolFlag::query()->create([
            'subject_type' => $subjectType->value,
            'subject_id' => $subjectId,
            'flag_type' => $type->value,
            'severity' => $severity ?? $type->defaultSeverity(),
            'status' => QuestPatrolFlagStatus::Open->value,
            'fingerprint' => $fingerprint,
            'meta' => $meta,
            'detected_at' => now(),
        ]);

        app(QuestPatrolAlertService::class)->notifyIfCritical($flag, true);

        return true;
    }

    /**
     * @return array<int, array{median: int, sample: int}>
     */
    private function categoryBands(): array
    {
        if ($this->categoryBands !== null) {
            return $this->categoryBands;
        }

        $this->categoryBands = Quest::query()
            ->where('budget_amount_minor', '>', 0)
            ->where('created_at', '>=', now()->subDays(180))
            ->select('quest_category_id', DB::raw('AVG(budget_amount_minor) as median'), DB::raw('COUNT(*) as sample'))
            ->groupBy('quest_category_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->quest_category_id => ['median' => (int) round((float) $row->median), 'sample' => (int) $row->sample]])
            ->all();

        return $this->categoryBands;
    }

    private function budgetDeviationPercent(Quest $quest): ?float
    {
        $categoryId = $quest->quest_category_id;
        if (! $categoryId) {
            return null;
        }

        $band = $this->categoryBands()[$categoryId] ?? null;
        if (! $band || $band['sample'] < 5 || $band['median'] <= 0) {
            return null;
        }

        return ((int) $quest->budget_amount_minor - $band['median']) / $band['median'] * 100;
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryFromFlags(Collection $flags): array
    {
        /** @var QuestPatrolFlag $top */
        $top = $flags->sortBy(fn (QuestPatrolFlag $f) => ['high' => 0, 'medium' => 1, 'low' => 2][$f->severity] ?? 3)->first();
        $type = $top->typeEnum();

        return [
            'signal' => $type?->label() ?? $top->flag_type,
            'risk_level' => $top->severity,
            'flags_count' => $flags->count(),
            'top_flag_type' => $top->flag_type,
            'detected_at' => $top->detected_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function flagPayload(QuestPatrolFlag $flag): array
    {
        $type = $flag->typeEnum();

        return [
            'id' => $flag->id,
            'type' => $flag->flag_type,
            'label' => $type?->label() ?? $flag->flag_type,
            'severity' => $flag->severity,
            'status' => $flag->status,
            'meta' => $flag->meta ?? [],
            'detected_at' => $flag->detected_at?->toIso8601String(),
            'recommendation' => $this->recommendationFor($flag),
        ];
    }

    private function recommendationFor(QuestPatrolFlag $flag): string
    {
        return match ($flag->flag_type) {
            QuestPatrolFlagType::BudgetAnomalyHigh->value,
            QuestPatrolFlagType::TierMismatch->value,
            QuestPatrolFlagType::InstantCompletion->value => 'Investigate client profile and check conversations for off-platform payment references.',
            QuestPatrolFlagType::WinRateAnomaly->value,
            QuestPatrolFlagType::InstantAward->value => 'Review award patterns for possible collusion.',
            default => 'Review details and dismiss if this is a false positive.',
        };
    }
}
