<?php

namespace App\Services\Operations;

use App\Models\QuestCategory;
use App\Models\State;
use App\Models\User;
use App\Services\Admin\AdminProposalModerationService;
use App\Services\Admin\AdminQuestModerationService;
use App\Services\Admin\ProposalManagementEngineService;
use App\Services\Admin\QuestManagementEngineService;
use App\Support\Operations\StaffCapabilities;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StaffModerationQueueService
{
    public function __construct(
        private readonly QuestManagementEngineService $quests,
        private readonly ProposalManagementEngineService $proposals,
    ) {}

    /**
     * @return list<array{key: string, label: string, hint: string, filter: array<string, mixed>}>
     */
    public function questQueues(): array
    {
        return [
            ['key' => 'all', 'label' => 'All quests', 'hint' => 'Every quest — browse, review, and edit', 'filter' => []],
            ['key' => 'open_live', 'label' => 'Open & live', 'hint' => 'Marketplace-open quests', 'filter' => ['quick' => 'open']],
            ['key' => 'needs_review', 'label' => 'Under review', 'hint' => 'Quests marked under admin review', 'filter' => ['admin_status' => 'under_review']],
            ['key' => 'flagged', 'label' => 'With flags', 'hint' => 'Quests with active moderation flags', 'filter' => ['quick' => 'flagged']],
            ['key' => 'notices', 'label' => 'With notices', 'hint' => 'Quests carrying user-visible admin notices', 'filter' => ['has_notices' => true]],
            ['key' => 'awaiting_edits', 'label' => 'Awaiting edits', 'hint' => 'Action required before the quest can proceed', 'filter' => ['admin_status' => 'action_required']],
            ['key' => 'escalated', 'label' => 'Escalated to Super Admin', 'hint' => 'Referred or restricted quests', 'filter' => ['admin_status' => 'referred']],
            ['key' => 'suspended', 'label' => 'Suspended', 'hint' => 'Quests suspended from the marketplace', 'filter' => ['admin_status' => 'suspended']],
        ];
    }

    /**
     * @return list<array{key: string, label: string, hint: string, filter: array<string, mixed>}>
     */
    public function proposalQueues(): array
    {
        return [
            ['key' => 'all', 'label' => 'All proposals', 'hint' => 'Every proposal — review, edit, or remove', 'filter' => []],
            ['key' => 'recent', 'label' => 'Last 24 hours', 'hint' => 'Recently submitted proposals', 'filter' => ['quick' => 'last_24h']],
            ['key' => 'flagged', 'label' => 'Flagged proposals', 'hint' => 'Proposals with active flags', 'filter' => ['quick' => 'manual_flagged']],
            ['key' => 'under_review', 'label' => 'Under review', 'hint' => 'Proposals in active review', 'filter' => ['admin_status' => 'under_review']],
            ['key' => 'action_required', 'label' => 'Action required', 'hint' => 'Proposals needing staff action', 'filter' => ['admin_status' => 'action_required']],
            ['key' => 'referred', 'label' => 'Referred to Super Admin', 'hint' => 'Escalated proposal cases', 'filter' => ['admin_status' => 'referred']],
            ['key' => 'high_risk', 'label' => 'High-risk proposals', 'hint' => 'Auto-flagged risk signals', 'filter' => ['quick' => 'auto_flagged']],
            ['key' => 'low_tier_high_value', 'label' => 'Low-tier on high-value quests', 'hint' => 'Tier mismatch risk queue', 'filter' => ['quick' => 'high_value_low_tier']],
        ];
    }

    public function questListing(Request $request): array
    {
        $request->merge(['per_page' => min(250, max(25, $request->integer('per_page', 100)))]);

        if ($request->boolean('has_notices')) {
            $request->merge(['has_notices' => '1']);
        }

        $paginator = $this->quests->listing($request);

        return [
            'items' => collect($paginator->items())->values()->all(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
            ],
        ];
    }

    public function proposalListing(Request $request): array
    {
        $request->merge(['per_page' => min(250, max(25, $request->integer('per_page', 100)))]);

        $paginator = $this->proposals->listing($request);

        return [
            'items' => collect($paginator->items())->values()->all(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
            ],
        ];
    }

    public function questDetail(int $questId): array
    {
        $quest = \App\Models\Quest::query()->findOrFail($questId);

        return $this->quests->detail($quest);
    }

    public function proposalDetail(int $proposalId): array
    {
        $proposal = \App\Models\QuestOffer::query()->findOrFail($proposalId);

        return $this->proposals->detail($proposal);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function staffAdmins(): array
    {
        return User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'super_admin']))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => ['value' => (string) $user->id, 'label' => $user->name.' · '.$user->email])
            ->values()
            ->all();
    }

    public function options(): array
    {
        $questStatuses = app(AdminQuestModerationService::class)->statusOptions();
        $proposalStatuses = app(AdminProposalModerationService::class)->statusOptions();
        return [
            'quest_admin_statuses' => array_values(array_filter(
                $questStatuses,
                fn (array $row) => StaffCapabilities::canSetQuestAdminStatus($row['value'] ?? ''),
            )),
            'proposal_admin_statuses' => array_values(array_filter(
                $proposalStatuses,
                fn (array $row) => StaffCapabilities::canSetProposalAdminStatus($row['value'] ?? ''),
            )),
            'staff_admins' => $this->staffAdmins(),
            'quest_edit' => [
                'categories' => QuestCategory::query()
                    ->where('status', 'active')
                    ->orderBy('parent_id')
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'parent_id'])
                    ->map(fn (QuestCategory $category) => [
                        'value' => $category->id,
                        'label' => $category->parent_id ? '— '.$category->name : $category->name,
                    ])
                    ->values()
                    ->all(),
                'states' => State::query()->orderBy('name')->get(['id', 'name'])->map(fn (State $state) => [
                    'value' => $state->id,
                    'label' => $state->name,
                ])->values()->all(),
            ],
            'proposal_flag_types' => [
                'off_platform_contact',
                'solicitation',
                'lowball_bid',
                'copy_paste',
                'velocity_spam',
                'coordinated_bidding',
                'high_value_low_tier',
                'prior_admin_actions',
                'policy_violation',
                'other',
            ],
        ];
    }
}
