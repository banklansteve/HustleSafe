<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\User;
use App\Services\Freelancer\FreelancerProSubscriptionService;
use App\Services\Proposals\ProposalClarificationInboxService;
use App\Services\Proposals\ProposalCompletenessScoreService;
use App\Services\Proposals\ProposalShortlistService;
use App\Services\Verification\VerificationEngineService;
use App\Support\PlatformSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuestClientProposalsController extends Controller
{
    public function index(
        Request $request,
        Quest $quest,
        ProposalShortlistService $shortlists,
        ProposalCompletenessScoreService $completeness,
        VerificationEngineService $verification,
    ): Response {
        $this->authorize('view', $quest);

        if ((int) $quest->client_id !== (int) $request->user()?->id) {
            abort(403);
        }

        $client = $request->user();

        $offers = QuestOffer::query()
            ->where('quest_id', $quest->id)
            ->visibleInClientInbox()
            ->with([
                'freelancer:id,first_name,last_name,name,slug,avatar_url,headline,verification_tier,current_verification_level,kyc_tier',
                'freelancer.trustMetrics:user_id,freelancer_trust_score',
            ])
            ->latest('created_at')
            ->limit(400)
            ->get();

        $proposals = $offers
            ->map(fn (QuestOffer $o) => self::proposalRow($quest, $o, $completeness, $verification, $client))
            ->values()
            ->all();

        $shortlistSettings = PlatformSettings::shortlistSettings();

        return Inertia::render('Quests/ClientQuestProposals', [
            'quest' => [
                'title' => $quest->title,
                'route_key' => $quest->getRouteKey(),
                'status' => $quest->status->value,
                'reference_code' => $quest->reference_code,
            ],
            'proposals' => $proposals,
            'shortlist_meta' => [
                'max' => $shortlistSettings['max_per_quest'],
                'count' => $shortlists->countForQuest($quest),
            ],
            'clarification_inbox' => app(ProposalClarificationInboxService::class)->forQuestOwner($quest, $client),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function proposalRow(
        Quest $quest,
        QuestOffer $o,
        ?ProposalCompletenessScoreService $completeness = null,
        ?VerificationEngineService $verification = null,
        ?User $client = null,
    ): array {
        $completeness ??= app(ProposalCompletenessScoreService::class);
        $verification ??= app(VerificationEngineService::class);
        $proMembership = app(FreelancerProSubscriptionService::class);

        $freelancer = $o->freelancer;
        $trustTier = $freelancer ? $verification->effectiveLevel($freelancer) : 0;
        $durationDays = $o->estimated_duration_days;
        $timelineLabel = null;
        if ($durationDays) {
            $timelineLabel = trans_choice(':count day|:count days', $durationDays, ['count' => $durationDays]);
        } elseif ($o->planned_finish_date && $o->planned_start_date) {
            try {
                $days = max(1, $o->planned_start_date->diffInDays($o->planned_finish_date));
                $durationDays = $days;
                $timelineLabel = trans_choice(':count day|:count days', $days, ['count' => $days]);
            } catch (\Throwable) {
                $timelineLabel = $o->planned_finish_date->toDateString();
            }
        } elseif ($o->planned_finish_date) {
            $timelineLabel = $o->planned_finish_date->toDateString();
        }

        return [
            'id' => $o->id,
            'status' => $o->status,
            'is_shortlisted' => $o->status === 'shortlisted',
            'created_at' => $o->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            'quoted_amount_minor' => (int) ($o->quoted_amount_minor ?? 0),
            'shortlisted_at' => $o->shortlisted_at?->timezone('Africa/Lagos')->toIso8601String(),
            'completeness_score' => $completeness->score($o),
            'timeline_days' => $durationDays,
            'timeline_label' => $timelineLabel,
            'trust_tier' => $trustTier,
            'trust_score' => (int) ($freelancer?->trustMetrics?->freelancer_trust_score ?? 0),
            'freelancer' => $freelancer ? [
                'name' => self::freelancerDisplayName($freelancer),
                'first_name' => $freelancer->first_name,
                'last_name' => $freelancer->last_name,
                'slug' => $freelancer->slug,
                'avatar_url' => $freelancer->avatar_url,
                'headline' => $freelancer->headline,
                'is_pro' => $proMembership->isPro($freelancer),
            ] : null,
            'show_url' => route('quests.proposals.show', [$quest->getRouteKey(), $o->id]),
            'clarification' => ($client ?? $quest->client)
                ? app(ProposalClarificationInboxService::class)->badgeForOffer($o, $client ?? $quest->client)
                : null,
        ];
    }

    public static function freelancerDisplayName(User $user): string
    {
        $name = trim((string) ($user->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $parts = array_filter([
            trim((string) ($user->first_name ?? '')),
            trim((string) ($user->last_name ?? '')),
        ]);

        return $parts !== [] ? implode(' ', $parts) : __('Freelancer');
    }
}
