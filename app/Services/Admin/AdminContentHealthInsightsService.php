<?php

namespace App\Services\Admin;

use App\Enums\AdminProposalStatus;
use App\Enums\AdminQuestStatus;
use App\Enums\ContractStatus;
use App\Enums\QuestPatrolFlagStatus;
use App\Enums\QuestStatus;
use App\Models\ContractPatrolFlag;
use App\Models\Quest;
use App\Models\QuestContract;
use App\Models\QuestOffer;
use App\Models\QuestPatrolFlag;
use App\Services\Admin\QuestPatrol\QuestPatrolAnomalyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AdminContentHealthInsightsService
{
    private const CACHE_TTL_SECONDS = 180;

    public function __construct(
        private readonly QuestPatrolAnomalyService $patrolAnomalies,
    ) {}

    /**
     * @return array{quests: array<string, mixed>, proposals: array<string, mixed>, contracts: array<string, mixed>}
     */
    public function summary(): array
    {
        return Cache::remember('admin.insights.content_health.summary', self::CACHE_TTL_SECONDS, function (): array {
            return [
                'quests' => $this->moduleSummary('quests'),
                'proposals' => $this->moduleSummary('proposals'),
                'contracts' => $this->moduleSummary('contracts'),
            ];
        });
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function drillDown(string $module, string $band, int $limit = 20): array
    {
        $band = in_array($band, ['healthy', 'warning', 'critical'], true) ? $band : 'critical';
        $limit = min(50, max(5, $limit));

        return match ($module) {
            'quests' => $this->questDrillDown($band, $limit),
            'proposals' => $this->proposalDrillDown($band, $limit),
            'contracts' => $this->contractDrillDown($band, $limit),
            default => ['items' => [], 'total' => 0],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function moduleSummary(string $module): array
    {
        $bands = match ($module) {
            'quests' => $this->questBandCounts(),
            'proposals' => $this->proposalBandCounts(),
            'contracts' => $this->contractBandCounts(),
            default => ['healthy' => 0, 'warning' => 0, 'critical' => 0, 'total' => 0],
        };

        $total = max(0, (int) ($bands['total'] ?? 0));
        $healthy = max(0, (int) ($bands['healthy'] ?? 0));
        $warning = max(0, (int) ($bands['warning'] ?? 0));
        $critical = max(0, (int) ($bands['critical'] ?? 0));

        return [
            'label' => match ($module) {
                'quests' => 'Quest health',
                'proposals' => 'Proposal health',
                'contracts' => 'Contract health',
                default => 'Content health',
            },
            'total' => $total,
            'healthy' => $healthy,
            'warning' => $warning,
            'critical' => $critical,
            'healthy_percent' => $total > 0 ? round(($healthy / $total) * 100, 1) : 100,
            'warning_percent' => $total > 0 ? round(($warning / $total) * 100, 1) : 0,
            'critical_percent' => $total > 0 ? round(($critical / $total) * 100, 1) : 0,
            'score' => $this->healthScore($healthy, $warning, $critical, $total),
            'status_label' => $this->statusLabel($healthy, $warning, $critical, $total),
        ];
    }

    /**
     * @return array{healthy: int, warning: int, critical: int, total: int}
     */
    private function questBandCounts(): array
    {
        $query = Quest::query()
            ->whereIn('status', [
                QuestStatus::Open->value,
                QuestStatus::Assigned->value,
                QuestStatus::InProgress->value,
            ]);

        $total = (int) (clone $query)->count();
        if ($total === 0) {
            return ['healthy' => 0, 'warning' => 0, 'critical' => 0, 'total' => 0];
        }

        $ranks = $this->worstRanksForPatrolSubjects('quest');
        $adminRanks = $this->adminQuestRanks();

        $critical = 0;
        $warning = 0;

        (clone $query)->select(['id', 'admin_status'])->orderBy('id')->chunkById(500, function ($quests) use (&$critical, &$warning, $ranks, $adminRanks): void {
            foreach ($quests as $quest) {
                $rank = max(
                    (int) ($ranks[(int) $quest->id] ?? 0),
                    (int) ($adminRanks[(int) $quest->id] ?? 0),
                    $this->adminQuestStatusRank($quest->admin_status),
                );

                if ($rank >= 3) {
                    $critical++;
                } elseif ($rank >= 2) {
                    $warning++;
                }
            }
        });

        $flagged = $critical + $warning;

        return [
            'healthy' => max(0, $total - $flagged),
            'warning' => $warning,
            'critical' => $critical,
            'total' => $total,
        ];
    }

    /**
     * @return array{healthy: int, warning: int, critical: int, total: int}
     */
    private function proposalBandCounts(): array
    {
        $since = now()->subDays(30);
        $query = QuestOffer::query()
            ->where('created_at', '>=', $since)
            ->whereNotIn('status', ['withdrawn', 'expired']);

        $total = (int) (clone $query)->count();
        if ($total === 0) {
            return ['healthy' => 0, 'warning' => 0, 'critical' => 0, 'total' => 0];
        }

        $ranks = $this->worstRanksForPatrolSubjects('proposal');
        $adminRanks = $this->adminProposalRanks();

        $critical = 0;
        $warning = 0;

        (clone $query)->select(['id', 'admin_status'])->orderBy('id')->chunkById(500, function ($proposals) use (&$critical, &$warning, $ranks, $adminRanks): void {
            foreach ($proposals as $proposal) {
                $rank = max(
                    (int) ($ranks[(int) $proposal->id] ?? 0),
                    (int) ($adminRanks[(int) $proposal->id] ?? 0),
                    $this->adminProposalStatusRank($proposal->admin_status),
                );

                if ($rank >= 3) {
                    $critical++;
                } elseif ($rank >= 2) {
                    $warning++;
                }
            }
        });

        $flagged = $critical + $warning;

        return [
            'healthy' => max(0, $total - $flagged),
            'warning' => $warning,
            'critical' => $critical,
            'total' => $total,
        ];
    }

    /**
     * @return array{healthy: int, warning: int, critical: int, total: int}
     */
    private function contractBandCounts(): array
    {
        if (! Schema::hasTable('contract_patrol_flags')) {
            $total = (int) QuestContract::query()
                ->whereIn('status', [
                    ContractStatus::PendingEscrow,
                    ContractStatus::Active,
                    ContractStatus::AmendmentPending,
                    ContractStatus::Disputed,
                ])
                ->count();

            return ['healthy' => $total, 'warning' => 0, 'critical' => 0, 'total' => $total];
        }

        $query = QuestContract::query()
            ->whereIn('status', [
                ContractStatus::PendingEscrow,
                ContractStatus::Active,
                ContractStatus::AmendmentPending,
                ContractStatus::Disputed,
            ]);

        $total = (int) (clone $query)->count();
        if ($total === 0) {
            return ['healthy' => 0, 'warning' => 0, 'critical' => 0, 'total' => 0];
        }

        $ranks = ContractPatrolFlag::query()
            ->whereIn('status', ['open', 'acknowledged'])
            ->selectRaw('quest_contract_id, MAX(CASE severity WHEN "critical" THEN 4 WHEN "high" THEN 3 WHEN "medium" THEN 2 ELSE 1 END) as severity_rank')
            ->groupBy('quest_contract_id')
            ->pluck('severity_rank', 'quest_contract_id')
            ->map(fn ($rank) => (int) $rank)
            ->all();

        $critical = 0;
        $warning = 0;

        foreach ($ranks as $rank) {
            if ($rank >= 3) {
                $critical++;
            } elseif ($rank >= 2) {
                $warning++;
            }
        }

        $flagged = count($ranks);

        return [
            'healthy' => max(0, $total - $flagged),
            'warning' => $warning,
            'critical' => $critical,
            'total' => $total,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function worstRanksForPatrolSubjects(string $subjectType): array
    {
        if (! Schema::hasTable('quest_patrol_flags')) {
            return [];
        }

        return QuestPatrolFlag::query()
            ->where('subject_type', $subjectType)
            ->where('status', QuestPatrolFlagStatus::Open->value)
            ->selectRaw('subject_id, MAX(CASE severity WHEN "high" THEN 3 WHEN "medium" THEN 2 ELSE 1 END) as severity_rank')
            ->groupBy('subject_id')
            ->pluck('severity_rank', 'subject_id')
            ->map(fn ($rank) => (int) $rank)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function adminQuestRanks(): array
    {
        if (! Schema::hasTable('admin_quest_flags')) {
            return [];
        }

        return DB::table('admin_quest_flags')
            ->where('status', 'open')
            ->selectRaw('quest_id, MAX(CASE priority WHEN "critical" THEN 4 WHEN "high" THEN 3 WHEN "medium" THEN 2 ELSE 1 END) as severity_rank')
            ->groupBy('quest_id')
            ->pluck('severity_rank', 'quest_id')
            ->map(fn ($rank) => (int) $rank)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function adminProposalRanks(): array
    {
        if (! Schema::hasTable('admin_proposal_flags')) {
            return [];
        }

        return DB::table('admin_proposal_flags')
            ->where('status', 'open')
            ->selectRaw('quest_offer_id, MAX(CASE priority WHEN "critical" THEN 4 WHEN "high" THEN 3 WHEN "medium" THEN 2 ELSE 1 END) as severity_rank')
            ->groupBy('quest_offer_id')
            ->pluck('severity_rank', 'quest_offer_id')
            ->map(fn ($rank) => (int) $rank)
            ->all();
    }

    private function adminQuestStatusRank(mixed $status): int
    {
        $value = $status instanceof AdminQuestStatus ? $status->value : (string) ($status ?? '');

        return match ($value) {
            AdminQuestStatus::Suspended->value => 4,
            AdminQuestStatus::ActionRequired->value, AdminQuestStatus::UnderReview->value => 2,
            default => 0,
        };
    }

    private function adminProposalStatusRank(mixed $status): int
    {
        $value = $status instanceof AdminProposalStatus ? $status->value : (string) ($status ?? '');

        return match ($value) {
            AdminProposalStatus::Suspended->value => 4,
            AdminProposalStatus::ActionRequired->value, AdminProposalStatus::UnderReview->value, AdminProposalStatus::Flagged->value => 2,
            default => 0,
        };
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    private function questDrillDown(string $band, int $limit): array
    {
        $items = [];
        $total = 0;

        Quest::query()
            ->whereIn('status', [
                QuestStatus::Open->value,
                QuestStatus::Assigned->value,
                QuestStatus::InProgress->value,
            ])
            ->select(['id', 'reference_code', 'slug', 'uuid', 'title', 'admin_status', 'admin_status_reason', 'status'])
            ->orderByDesc('id')
            ->chunkById(200, function ($quests) use (&$items, &$total, $band, $limit): void {
                $ranks = $this->worstRanksForPatrolSubjects('quest');
                $adminRanks = $this->adminQuestRanks();

                foreach ($quests as $quest) {
                    $rank = max(
                        (int) ($ranks[(int) $quest->id] ?? 0),
                        (int) ($adminRanks[(int) $quest->id] ?? 0),
                        $this->adminQuestStatusRank($quest->admin_status),
                    );
                    $itemBand = $this->rankToBand($rank);

                    if ($itemBand !== $band) {
                        continue;
                    }

                    $total++;
                    if (count($items) >= $limit) {
                        continue;
                    }

                    $flagContext = $this->questFlagContext($quest);

                    $items[] = [
                        'id' => $quest->id,
                        'reference' => $quest->reference_code ?: ('Q-'.$quest->id),
                        'title' => $quest->title,
                        'signal' => $flagContext['signal'],
                        'reason' => $flagContext['reason'],
                        'reasons' => $flagContext['reasons'],
                        'url' => route('admin.moderation.index', [
                            'module' => 'quests',
                            'q' => $quest->reference_code ?: (string) $quest->id,
                            'open' => $quest->id,
                        ]),
                    ];
                }
            });

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    private function proposalDrillDown(string $band, int $limit): array
    {
        $items = [];
        $total = 0;
        $since = now()->subDays(30);

        QuestOffer::query()
            ->where('created_at', '>=', $since)
            ->whereNotIn('status', ['withdrawn', 'expired'])
            ->with(['quest:id,title,reference_code'])
            ->select(['id', 'quest_id', 'admin_status', 'admin_status_reason', 'status', 'pitch'])
            ->orderByDesc('id')
            ->chunkById(200, function ($proposals) use (&$items, &$total, $band, $limit): void {
                $ranks = $this->worstRanksForPatrolSubjects('proposal');
                $adminRanks = $this->adminProposalRanks();

                foreach ($proposals as $proposal) {
                    $rank = max(
                        (int) ($ranks[(int) $proposal->id] ?? 0),
                        (int) ($adminRanks[(int) $proposal->id] ?? 0),
                        $this->adminProposalStatusRank($proposal->admin_status),
                    );
                    $itemBand = $this->rankToBand($rank);

                    if ($itemBand !== $band) {
                        continue;
                    }

                    $total++;
                    if (count($items) >= $limit) {
                        continue;
                    }

                    $flagContext = $this->proposalFlagContext($proposal);

                    $items[] = [
                        'id' => $proposal->id,
                        'reference' => 'HSP-'.$proposal->id,
                        'title' => $proposal->quest?->title ?: \Illuminate\Support\Str::limit(strip_tags((string) $proposal->pitch), 80),
                        'signal' => $flagContext['signal'],
                        'reason' => $flagContext['reason'],
                        'reasons' => $flagContext['reasons'],
                        'url' => route('admin.moderation.index', [
                            'module' => 'proposals',
                            'q' => (string) $proposal->id,
                            'open' => $proposal->id,
                        ]),
                    ];
                }
            });

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    private function contractDrillDown(string $band, int $limit): array
    {
        if (! Schema::hasTable('contract_patrol_flags')) {
            return ['items' => [], 'total' => 0];
        }

        $items = [];
        $total = 0;

        QuestContract::query()
            ->whereIn('status', [
                ContractStatus::PendingEscrow,
                ContractStatus::Active,
                ContractStatus::AmendmentPending,
                ContractStatus::Disputed,
            ])
            ->with(['quest:id,title'])
            ->select(['id', 'reference_code', 'quest_id', 'status'])
            ->orderByDesc('id')
            ->chunkById(200, function ($contracts) use (&$items, &$total, $band, $limit): void {
                $flagSummaries = ContractPatrolFlag::query()
                    ->whereIn('quest_contract_id', $contracts->pluck('id'))
                    ->whereIn('status', ['open', 'acknowledged'])
                    ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
                    ->get()
                    ->groupBy('quest_contract_id');

                foreach ($contracts as $contract) {
                    $flags = $flagSummaries->get($contract->id, collect());
                    $rank = $flags->isEmpty()
                        ? 0
                        : (int) $flags->max(fn ($flag) => match ((string) $flag->severity) {
                            'critical' => 4,
                            'high' => 3,
                            'medium' => 2,
                            default => 1,
                        });

                    $itemBand = $this->rankToBand($rank);
                    if ($itemBand !== $band) {
                        continue;
                    }

                    $total++;
                    if (count($items) >= $limit) {
                        continue;
                    }

                    $topFlag = $flags->first();
                    $reasons = $flags->map(fn (ContractPatrolFlag $flag) => [
                        'source' => 'patrol',
                        'label' => $flag->flag_type instanceof \App\Enums\ContractPatrolFlagType
                            ? $flag->flag_type->label()
                            : (string) $flag->flag_type,
                        'reason' => (string) ($flag->summary ?: 'Patrol review required'),
                        'severity' => (string) $flag->severity,
                    ])->values()->all();

                    $items[] = [
                        'id' => $contract->id,
                        'reference' => $contract->reference_code,
                        'title' => $contract->quest?->title ?: 'Contract',
                        'signal' => $topFlag?->flag_type instanceof \App\Enums\ContractPatrolFlagType
                            ? $topFlag->flag_type->label()
                            : ($topFlag?->summary ?: 'Patrol review required'),
                        'reason' => (string) ($topFlag?->summary ?: 'Patrol review required'),
                        'reasons' => $reasons,
                        'url' => route('admin.contract-management.index', [
                            'q' => $contract->reference_code,
                            'open' => $contract->reference_code,
                        ]),
                    ];
                }
            });

        return ['items' => $items, 'total' => $total];
    }

    private function rankToBand(int $rank): string
    {
        if ($rank >= 3) {
            return 'critical';
        }

        if ($rank >= 2) {
            return 'warning';
        }

        return 'healthy';
    }

    private function healthScore(int $healthy, int $warning, int $critical, int $total): int
    {
        if ($total <= 0) {
            return 100;
        }

        return (int) round((($healthy * 100) + ($warning * 52) + ($critical * 12)) / $total);
    }

    private function statusLabel(int $healthy, int $warning, int $critical, int $total): string
    {
        $score = $this->healthScore($healthy, $warning, $critical, $total);

        if ($score >= 82) {
            return 'Healthy';
        }

        if ($score >= 58) {
            return 'Needs review';
        }

        return 'At risk';
    }

    /**
     * @return array{signal: string, reason: string, reasons: list<array<string, mixed>>}
     */
    private function questFlagContext(Quest $quest): array
    {
        $reasons = $this->patrolAnomalies->openFlagReasonRowsForQuest((int) $quest->id);

        if (Schema::hasTable('admin_quest_flags')) {
            $adminFlags = DB::table('admin_quest_flags')
                ->where('quest_id', $quest->id)
                ->where('status', 'open')
                ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
                ->limit(3)
                ->get(['type', 'priority', 'description']);

            foreach ($adminFlags as $flag) {
                $reasons[] = [
                    'source' => 'admin_flag',
                    'label' => str_replace('_', ' ', ucfirst((string) $flag->type)),
                    'reason' => (string) $flag->description,
                    'severity' => (string) $flag->priority,
                ];
            }
        }

        $statusRank = $this->adminQuestStatusRank($quest->admin_status);
        if ($statusRank >= 2) {
            $statusValue = $quest->admin_status instanceof AdminQuestStatus ? $quest->admin_status->value : (string) ($quest->admin_status ?? '');
            $reasons[] = [
                'source' => 'admin_status',
                'label' => str_replace('_', ' ', ucfirst($statusValue ?: 'admin review')),
                'reason' => trim((string) ($quest->admin_status_reason ?? '')) ?: 'Quest is under admin moderation review.',
                'severity' => $statusRank >= 4 ? 'critical' : 'medium',
            ];
        }

        $top = $reasons[0] ?? null;

        return [
            'signal' => $top['label'] ?? 'Admin review required',
            'reason' => $top['reason'] ?? 'Open patrol or admin flags require review.',
            'reasons' => $reasons,
        ];
    }

    /**
     * @return array{signal: string, reason: string, reasons: list<array<string, mixed>>}
     */
    private function proposalFlagContext(QuestOffer $proposal): array
    {
        $reasons = $this->patrolAnomalies->openFlagReasonRowsForProposal((int) $proposal->id);

        if (Schema::hasTable('admin_proposal_flags')) {
            $adminFlags = DB::table('admin_proposal_flags')
                ->where('quest_offer_id', $proposal->id)
                ->where('status', 'open')
                ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
                ->limit(3)
                ->get(['type', 'priority', 'description']);

            foreach ($adminFlags as $flag) {
                $reasons[] = [
                    'source' => 'admin_flag',
                    'label' => str_replace('_', ' ', ucfirst((string) $flag->type)),
                    'reason' => (string) $flag->description,
                    'severity' => (string) $flag->priority,
                ];
            }
        }

        $statusRank = $this->adminProposalStatusRank($proposal->admin_status);
        if ($statusRank >= 2) {
            $statusValue = $proposal->admin_status instanceof AdminProposalStatus ? $proposal->admin_status->value : (string) ($proposal->admin_status ?? '');
            $reasons[] = [
                'source' => 'admin_status',
                'label' => str_replace('_', ' ', ucfirst($statusValue ?: 'admin review')),
                'reason' => trim((string) ($proposal->admin_status_reason ?? '')) ?: 'Proposal is under admin moderation review.',
                'severity' => $statusRank >= 4 ? 'critical' : 'medium',
            ];
        }

        $top = $reasons[0] ?? null;

        return [
            'signal' => $top['label'] ?? 'Admin review required',
            'reason' => $top['reason'] ?? 'Open patrol or admin flags require review.',
            'reasons' => $reasons,
        ];
    }
}
