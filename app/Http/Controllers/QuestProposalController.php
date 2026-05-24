<?php

namespace App\Http\Controllers;

use App\Enums\AdminProposalStatus;
use App\Enums\QuestStatus;
use App\Enums\QuestVisibility;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Notifications\ProposalViewedMilestoneNotification;
use App\Services\FreelancerWorkspaceReadinessService;
use App\Services\QuestProposalPricingHintService;
use App\Services\UserNotificationInboxService;
use App\Support\PlatformSettings;
use App\Support\QuestCommerceUi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuestProposalController extends Controller
{
    public function create(Request $request, Quest $quest, FreelancerWorkspaceReadinessService $workspace): Response|RedirectResponse
    {
        $this->authorize('view', $quest);

        $user = $request->user();
        if ($user === null || $user->role?->slug !== 'freelancer') {
            abort(403);
        }

        $active = $quest->offers()
            ->where('freelancer_id', $user->id)
            ->whereIn('status', ['submitted', 'shortlisted', 'accepted'])
            ->when(Schema::hasColumn('quest_offers', 'admin_status'), function ($query): void {
                $query->whereNull('admin_status')
                    ->orWhere('admin_status', '!=', AdminProposalStatus::Suspended->value);
            })
            ->first();

        if ($active) {
            return redirect()->route('quests.proposals.show', [$quest, $active]);
        }

        $summary = $workspace->summarize($user);
        $inviteOffer = $quest->visibility === QuestVisibility::InviteOnly
            && $quest->isInvitedFreelancer($user);

        $canOffer = ($summary['can_submit_proposals'] ?? false)
            && ($workspace->matchesQuestCategory($user, $quest) || $inviteOffer);

        if (! $canOffer) {
            return redirect()
                ->route('quests.show', $quest)
                ->with('status', __('You are not eligible to propose on this quest yet.'));
        }

        try {
            $workspace->assertCanSubmitOffer($user, $quest, null);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first();

            return redirect()
                ->route('quests.show', $quest)
                ->with('status', $msg ?: __('You are not eligible to propose on this quest yet.'));
        }

        $quest->loadMissing([
            'client:id,first_name,name,slug,avatar_url',
            'questCategory:id,name,parent_id',
            'questCategory.parent:id,name',
            'stateModel:id,name',
            'localGovernment:id,name',
        ]);

        $hints = app(QuestController::class)->questCreateStatsHints();
        $cid = (string) (int) ($quest->quest_category_id ?? 0);
        $catHints = $hints['by_category'][$cid] ?? null;
        $pricingHints = app(QuestProposalPricingHintService::class)->hintsFor($user, $quest);

        return Inertia::render('Quests/Proposals/Create', [
            'quest' => $this->questComposerPayload($quest),
            'workspace' => array_merge(['enabled' => true], $summary),
            'market_hints' => [
                'category' => $catHints,
                'global_budget' => $hints['global_budget'] ?? null,
                'global_completion' => $hints['global_completion'] ?? null,
            ],
            'pricing_hints' => $pricingHints,
            'vat_preset_percent' => (float) config('quests.proposal_vat_percent', 7.5),
            'proposal_edit' => null,
        ]);
    }

    public function edit(Request $request, Quest $quest, QuestOffer $offer, FreelancerWorkspaceReadinessService $workspace): Response|RedirectResponse
    {
        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $this->authorize('view', $quest);

        $user = $request->user();
        if ($user === null || $user->role?->slug !== 'freelancer' || (int) $offer->freelancer_id !== (int) $user->id) {
            abort(403);
        }

        if (! in_array($offer->status, ['submitted', 'shortlisted'], true)) {
            return redirect()->route('quests.proposals.show', [$quest, $offer])
                ->with('status', __('This proposal can no longer be edited.'));
        }

        if ($offer->freelancer_edit_deadline_at !== null && now()->greaterThan($offer->freelancer_edit_deadline_at)) {
            return redirect()->route('quests.proposals.show', [$quest, $offer])
                ->with('status', __('The edit window for this proposal has closed.'));
        }

        $quest->loadMissing([
            'client:id,first_name,name,slug,avatar_url',
            'questCategory:id,name,parent_id',
            'questCategory.parent:id,name',
            'stateModel:id,name',
            'localGovernment:id,name',
        ]);

        $hints = app(QuestController::class)->questCreateStatsHints();
        $cid = (string) (int) ($quest->quest_category_id ?? 0);
        $catHints = $hints['by_category'][$cid] ?? null;
        $summary = $workspace->summarize($user);
        $pricingHints = app(QuestProposalPricingHintService::class)->hintsFor($user, $quest);

        return Inertia::render('Quests/Proposals/Create', [
            'quest' => $this->questComposerPayload($quest),
            'workspace' => array_merge(['enabled' => true], $summary),
            'market_hints' => [
                'category' => $catHints,
                'global_budget' => $hints['global_budget'] ?? null,
                'global_completion' => $hints['global_completion'] ?? null,
            ],
            'pricing_hints' => $pricingHints,
            'vat_preset_percent' => (float) config('quests.proposal_vat_percent', 7.5),
            'platform_fee_percent' => PlatformSettings::platformFeePercent(),
            'proposal_edit' => $this->proposalEditPayload($quest, $offer),
        ]);
    }

    public function show(Request $request, Quest $quest, QuestOffer $offer, UserNotificationInboxService $inbox): Response
    {
        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $this->authorize('view', $offer);

        $quest->loadMissing([
            'client:id,first_name,name,slug,avatar_url,username',
            'questCategory:id,name,parent_id',
            'questCategory.parent:id,name',
            'stateModel:id,name',
            'localGovernment:id,name',
            'acceptedOffer',
        ]);
        $offer->loadMissing(['freelancer:id,first_name,name,slug,avatar_url,username,headline']);

        $user = $request->user();
        $isClient = $user && (int) $user->id === (int) $quest->client_id;
        $isFreelancerAuthor = $user && (int) $user->id === (int) $offer->freelancer_id;
        $isObserver = $user
            && ! $isClient
            && ! $isFreelancerAuthor
            && ! in_array($user->role?->slug, ['admin', 'super_admin'], true);

        if ($isClient) {
            $inbox->markQuestProposalForOffer($user, (int) $quest->id, (int) $offer->id);

            if (Schema::hasColumn('quest_offers', 'client_view_count')) {
                $offer->increment('client_view_count');
                $offer->forceFill(['last_client_view_at' => now()])->saveQuietly();
                $count = (int) $offer->client_view_count;
                if (in_array($count, [1, 2, 5, 10, 25, 50], true)) {
                    $offer->freelancer?->notify(new ProposalViewedMilestoneNotification($offer->fresh(), $count));
                }
            }
        }

        $commerce = QuestCommerceUi::disputeForQuest($quest, $user);
        $commerce = array_merge($commerce, QuestCommerceUi::fundingForOffer($quest, $offer, $user));

        return Inertia::render('Quests/Proposals/Show', [
            'quest' => $this->questComposerPayload($quest),
            'offer' => $this->offerPayload($offer, $quest, $isObserver),
            'is_client' => (bool) $isClient,
            'is_author' => (bool) $isFreelancerAuthor,
            'observer_mode' => (bool) $isObserver,
            'can_download_pdf' => (bool) ($user?->can('downloadPdf', $offer) ?? false),
            'conversation_with_freelancer_url' => $isClient && $offer->freelancer?->slug
                ? route('quests.messages.show', [$quest->getRouteKey(), $offer->freelancer->slug])
                : null,
            'commerce' => $commerce,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function proposalEditPayload(Quest $quest, QuestOffer $offer): array
    {
        $p = $offer->pricing_snapshot ?? [];
        $materials = [];
        foreach ($offer->materials ?? [] as $m) {
            $qtyRaw = $m['quantity'] ?? '1';
            $lineMinor = (int) ($m['line_total_minor'] ?? $m['cost_minor'] ?? 0);
            $lineNgn = (int) round($lineMinor / 100);
            $qty = is_numeric($qtyRaw) ? (float) $qtyRaw : (float) str_replace(',', '.', preg_replace('/[^0-9.,\-]/', '', (string) $qtyRaw));
            if (! is_finite($qty) || $qty <= 0) {
                $qty = 1.0;
            }
            $unitNgn = (int) max(0, round($lineNgn / $qty));
            $materials[] = [
                'label' => (string) ($m['label'] ?? ''),
                'quantity' => (string) $qtyRaw,
                'unit_price_ngn' => $unitNgn,
            ];
        }
        if ($materials === []) {
            $materials[] = ['label' => '', 'quantity' => '1', 'unit_price_ngn' => 0];
        }

        $pricing = [
            'professional_fee_ngn' => (int) round(((int) ($p['professional_fee_minor'] ?? 0)) / 100),
            'vat_applies' => (bool) ($p['vat_applies'] ?? true),
            'withholding_tax_percent' => (float) ($p['withholding_tax_percent'] ?? 0),
            'travel_cost_ngn' => (int) round(((int) ($p['travel_cost_minor'] ?? 0)) / 100),
            'stamp_duty_ngn' => (int) round(((int) ($p['stamp_duty_minor'] ?? 0)) / 100),
            'platform_fee_ngn' => (int) round(((int) ($p['platform_fee_minor'] ?? 0)) / 100),
            'discount_ngn' => (int) round(((int) ($p['discount_minor'] ?? 0)) / 100),
            'grand_total_ngn' => (int) ($p['grand_total_ngn'] ?? round(((int) ($p['grand_total_minor'] ?? 0)) / 100)),
        ];

        return [
            'offer_id' => $offer->id,
            'pitch' => $offer->pitch,
            'scope_detail' => $offer->scope_detail,
            'warranty_terms' => $offer->warranty_terms ?? '',
            'planned_start_date' => $offer->planned_start_date?->toDateString(),
            'planned_finish_date' => $offer->planned_finish_date?->toDateString(),
            'estimated_duration_days' => $offer->estimated_duration_days,
            'corrections_included' => (bool) $offer->corrections_included,
            'corrections_rounds' => $offer->corrections_rounds,
            'progress_report_frequency' => $offer->progress_report_frequency ?? 'weekly',
            'materials' => $materials,
            'pricing' => $pricing,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function questComposerPayload(Quest $quest): array
    {
        return [
            'title' => $quest->title,
            'slug' => $quest->slug,
            'uuid' => $quest->uuid,
            'route_key' => $quest->getRouteKey(),
            'quest_category_id' => $quest->quest_category_id,
            'budget_minor' => (int) ($quest->budget_amount_minor ?? 0),
            'estimated_completion_days' => $quest->estimated_completion_days,
            'due_at' => $quest->due_at?->timezone('Africa/Lagos')->toIso8601String(),
            'category' => $quest->questCategory ? [
                'name' => $quest->questCategory->name,
                'parent_name' => $quest->questCategory->parent?->name,
            ] : null,
            'location' => [
                'state' => $quest->stateModel?->name,
                'lga' => $quest->localGovernment?->name,
                'city' => $quest->city,
            ],
            'client' => [
                'name' => $quest->client?->name,
                'first_name' => $quest->client?->first_name,
            ],
            'escrow_status' => $quest->escrow_status ?? 'none',
            'status' => $quest->status instanceof QuestStatus ? $quest->status->value : (string) $quest->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function offerPayload(QuestOffer $offer, Quest $quest, bool $observerMode = false): array
    {
        $p = $offer->pricing_snapshot ?? [];

        if ($observerMode) {
            return [
                'id' => $offer->id,
                'status' => $offer->status,
                'pitch' => Str::limit((string) ($offer->pitch ?? ''), 450),
                'scope_detail' => Str::limit((string) ($offer->scope_detail ?? ''), 520),
                'warranty_terms' => null,
                'proposed_completion_date' => $offer->proposed_completion_date?->toDateString(),
                'planned_start_date' => $offer->planned_start_date?->toDateString(),
                'planned_finish_date' => $offer->planned_finish_date?->toDateString(),
                'estimated_duration_days' => $offer->estimated_duration_days,
                'corrections_included' => (bool) $offer->corrections_included,
                'corrections_rounds' => $offer->corrections_rounds,
                'progress_report_frequency' => $offer->progress_report_frequency,
                'materials' => [],
                'pricing_snapshot' => [],
                'quoted_amount_minor' => null,
                'created_at' => $offer->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                'client_view_count' => 0,
                'last_client_view_at' => null,
                'client_pinned_at' => false,
                'shortlisted_at' => null,
                'freelancer_edit_deadline_at' => null,
                'can_edit' => false,
                'freelancer' => $offer->freelancer ? [
                    'name' => $offer->freelancer->name,
                    'first_name' => $offer->freelancer->first_name,
                    'slug' => $offer->freelancer->slug,
                    'avatar_url' => $offer->freelancer->avatar_url,
                    'headline' => $offer->freelancer->headline,
                ] : null,
                'platform_fee_percent_display' => PlatformSettings::platformFeePercent(),
            ];
        }

        return [
            'id' => $offer->id,
            'status' => $offer->status,
            'pitch' => $offer->pitch,
            'scope_detail' => $offer->scope_detail,
            'warranty_terms' => $offer->warranty_terms,
            'proposed_completion_date' => $offer->proposed_completion_date?->toDateString(),
            'planned_start_date' => $offer->planned_start_date?->toDateString(),
            'planned_finish_date' => $offer->planned_finish_date?->toDateString(),
            'estimated_duration_days' => $offer->estimated_duration_days,
            'corrections_included' => (bool) $offer->corrections_included,
            'corrections_rounds' => $offer->corrections_rounds,
            'progress_report_frequency' => $offer->progress_report_frequency,
            'materials' => $offer->materials ?? [],
            'pricing_snapshot' => $p,
            'quoted_amount_minor' => (int) ($offer->quoted_amount_minor ?? ($p['grand_total_minor'] ?? 0)),
            'created_at' => $offer->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            'client_view_count' => (int) ($offer->client_view_count ?? 0),
            'last_client_view_at' => $offer->last_client_view_at?->timezone('Africa/Lagos')->toIso8601String(),
            'client_pinned_at' => $offer->client_pinned_at !== null,
            'shortlisted_at' => $offer->shortlisted_at?->timezone('Africa/Lagos')->toIso8601String(),
            'freelancer_edit_deadline_at' => $offer->freelancer_edit_deadline_at?->timezone('Africa/Lagos')->toIso8601String(),
            'can_edit' => $offer->freelancer_edit_deadline_at !== null
                && now()->lessThanOrEqualTo($offer->freelancer_edit_deadline_at)
                && in_array($offer->status, ['submitted', 'shortlisted'], true),
            'freelancer' => $offer->freelancer ? [
                'name' => $offer->freelancer->name,
                'first_name' => $offer->freelancer->first_name,
                'slug' => $offer->freelancer->slug,
                'avatar_url' => $offer->freelancer->avatar_url,
                'headline' => $offer->freelancer->headline,
            ] : null,
            'platform_fee_percent_display' => (float) config('quests.platform_fee_percent_display', 5),
        ];
    }
}
