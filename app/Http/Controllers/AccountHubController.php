<?php

namespace App\Http\Controllers;

use App\Enums\PortfolioStatus;
use App\Enums\ReviewStatus;
use App\Enums\QuestDisputeStatus;
use App\Models\Portfolio;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\QuestDispute;
use App\Models\Review;
use App\Models\State;
use App\Models\UserFollow;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class AccountHubController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $user->load([
            'role',
            'stateModel:id,name',
            'localGovernmentModel:id,name',
            'freelancerBusinessProfile',
            'freelancerCredentials',
            'questCategoryPreferences:id,name,parent_id',
        ]);

        $role = $user->role?->slug ?? 'client';
        $isFreelancer = $role === 'freelancer';

        $tab = (string) $request->query('tab', 'overview');
        $allowedTabs = ['overview', 'reviews', 'portfolio', 'credentials', 'visibility', 'settings'];
        if (! in_array($tab, $allowedTabs, true)) {
            $tab = 'overview';
        }
        if (! $isFreelancer && in_array($tab, ['portfolio', 'credentials'], true)) {
            $tab = 'overview';
        }

        $reviewBase = Review::query()
            ->where('reviewee_id', $user->id)
            ->where('status', ReviewStatus::Published);

        $distribution = (clone $reviewBase)
            ->whereNotNull('rating')
            ->selectRaw('rating, COUNT(*) as c')
            ->groupBy('rating')
            ->pluck('c', 'rating')
            ->all();

        $dist = [];
        for ($i = 1; $i <= 5; $i++) {
            $dist[(string) $i] = (int) ($distribution[$i] ?? 0);
        }

        $recentReviews = (clone $reviewBase)
            ->with(['quest:id,title', 'reviewer:id,first_name,name', 'attachments'])
            ->latest('created_at')
            ->limit(8)
            ->get();

        $portfolioPreview = [];
        $portfolioCounts = ['published' => 0, 'draft' => 0];
        if ($isFreelancer) {
            $portfolioCounts = [
                'published' => Portfolio::query()->where('user_id', $user->id)->where('status', PortfolioStatus::Published)->count(),
                'draft' => Portfolio::query()->where('user_id', $user->id)->where('status', PortfolioStatus::Draft)->count(),
            ];
            $portfolioPreview = Portfolio::query()
                ->where('user_id', $user->id)
                ->where('status', PortfolioStatus::Published)
                ->latest('published_at')
                ->limit(4)
                ->get()
                ->map(fn (Portfolio $p) => [
                    'slug' => $p->slug,
                    'title' => $p->title,
                    'cover_url' => $p->coverUrl(),
                    'favorites_count' => (int) $p->favorites_count,
                ])
                ->all();
        }

        return Inertia::render('Account/Hub', [
            'activeTab' => $tab,
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'slug' => $user->slug,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
                'headline' => $user->headline,
                'bio' => $user->bio,
                'profession' => $user->profession,
                'job_title' => $user->job_title,
                'company_name' => $user->company_name,
                'years_experience' => $user->years_experience,
                'hourly_rate_min' => $user->hourly_rate_min,
                'hourly_rate_max' => $user->hourly_rate_max,
                'city' => $user->city,
                'address_line' => $user->address_line,
                'state_id' => $user->state_id,
                'local_government_id' => $user->local_government_id,
                'verification_tier' => $user->verification_tier,
                'created_at' => $user->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                'state' => $user->stateModel?->name,
                'local_government' => $user->localGovernmentModel?->name,
                'role_slug' => $role,
                'account_type' => $user->account_type,
                'hide_online_presence' => (bool) $user->hide_online_presence,
            ],
            'trust' => [
                'freelancer' => $user->trust_score,
                'client' => $user->client_trust_score,
                'avg_rating_freelancer' => $user->avg_rating_as_freelancer,
                'rating_count_freelancer' => $user->ratings_count_as_freelancer,
                'avg_rating_client' => $user->avg_rating_as_client,
                'rating_count_client' => $user->ratings_count_as_client,
                'profile_percent' => $user->profile_completion_percent,
            ],
            'reviewStats' => [
                'total' => (clone $reviewBase)->count(),
                'with_stars' => (clone $reviewBase)->whereNotNull('rating')->count(),
                'distribution' => $dist,
            ],
            'recentReviews' => $recentReviews->map(fn (Review $r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'title' => $r->title,
                'comment' => $r->comment,
                'created_at' => $r->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                'quest_title' => $r->quest?->title,
                'reviewer_label' => $r->reviewer?->first_name ?: $r->reviewer?->name,
                'attachments' => $r->attachments->map(fn ($a) => [
                    'url' => $a->url(),
                    'original_name' => $a->original_name,
                ])->all(),
            ])->all(),
            'portfolio' => [
                'counts' => $portfolioCounts,
                'preview' => $portfolioPreview,
            ],
            'categories' => $user->questCategoryPreferences->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ])->values()->all(),
            'credentials' => $user->freelancerCredentials->map(fn ($c) => [
                'id' => $c->id,
                'credential_type' => $c->credential_type,
                'title' => $c->title,
                'issuing_authority' => $c->issuing_authority,
                'reference_number' => $c->reference_number,
                'issued_on' => $c->issued_on?->toDateString(),
                'expires_on' => $c->expires_on?->toDateString(),
                'coverage_summary' => $c->coverage_summary,
                'is_verified' => $c->is_verified,
                'is_public' => $c->is_public,
            ])->all(),
            'cac' => $user->freelancerBusinessProfile ? [
                'registration_number' => $user->freelancerBusinessProfile->cac_registration_number,
                'status' => $user->freelancerBusinessProfile->cac_verification_status,
                'verified_at' => $user->freelancerBusinessProfile->cac_verified_at?->toIso8601String(),
            ] : null,
            'visibility' => $user->effectivePublicProfileSettings(),
            'visibilityKeys' => $isFreelancer
                ? array_keys(config('profile.public_defaults', []))
                : array_keys(config('profile.client_public_defaults', [])),
            'publicReviewsUrl' => $isFreelancer ? route('freelancers.public.reviews', $user->slug) : null,
            'publicPortfoliosUrl' => $isFreelancer ? route('freelancers.public.portfolios', $user->slug) : null,
            'follower_count' => Schema::hasTable('user_follows')
                ? UserFollow::query()->where('following_id', $user->id)->count()
                : 0,
            'following_count' => Schema::hasTable('user_follows')
                ? UserFollow::query()->where('follower_id', $user->id)->count()
                : 0,
            'visibilityFieldHelp' => $isFreelancer
                ? __('More you show publicly, the easier it is for clients to trust you and invite you to quests.')
                : __('Choose what freelancers and teammates can see about you. Sharing a bit more often makes collaboration smoother.'),
            'questCategoryTree' => $isFreelancer
                ? QuestCategory::query()
                    ->whereNull('parent_id')
                    ->with(['children' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug'])
                    ->map(fn (QuestCategory $p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'slug' => $p->slug,
                        'children' => $p->children->map(fn (QuestCategory $c) => [
                            'id' => $c->id,
                            'name' => $c->name,
                            'slug' => $c->slug,
                        ])->values()->all(),
                    ])
                    ->values()
                    ->all()
                : [],
            'locations' => State::query()
                ->with(['localGovernments:id,state_id,name'])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (State $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'local_governments' => $s->localGovernments->map(fn ($lg) => [
                        'id' => $lg->id,
                        'name' => $lg->name,
                        'state_id' => $lg->state_id,
                    ])->values()->all(),
                ])
                ->values()
                ->all(),
            'commerce_hub' => [
                'disputes_index_url' => route('disputes.index'),
                'open_disputes_count' => Schema::hasTable('quest_disputes')
                    ? QuestDispute::query()
                        ->whereHas('quest', function ($q) use ($user): void {
                            $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id);
                        })
                        ->whereNotIn('status', [QuestDisputeStatus::Resolved, QuestDisputeStatus::ClosedWithdrawn])
                        ->count()
                    : 0,
                'pending_funding_quests_count' => Quest::query()
                    ->where('client_id', $user->id)
                    ->where('escrow_status', 'awaiting_funding')
                    ->whereNotNull('accepted_quest_offer_id')
                    ->count(),
            ],
        ]);
    }
}
