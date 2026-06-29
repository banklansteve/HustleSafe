<?php

namespace App\Http\Controllers\Public;

use App\Enums\PortfolioStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use App\Models\Review;
use App\Models\User;
use App\Models\UserFollow;
use App\Services\Freelancer\FreelancerProSubscriptionService;
use App\Services\PowerHoursService;
use App\Services\Verification\VerificationEngineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublicFreelancerProfileController extends Controller
{
    public function __invoke(Request $request, string $slug): RedirectResponse|Response
    {
        $user = User::query()
            ->where('slug', $slug)
            ->whereHas('role', fn ($q) => $q->where('slug', 'freelancer'))
            ->with([
                'stateModel',
                'localGovernmentModel',
                'freelancerBusinessProfile',
                'freelancerCredentials' => fn ($q) => $q->where('is_public', true),
            ])
            ->first();

        if ($user === null) {
            throw new NotFoundHttpException;
        }

        $viewer = $request->user();
        if ($viewer !== null && $viewer->id === $user->id) {
            return redirect()->route('account.show');
        }

        $settings = $user->effectivePublicProfileSettings();

        if ($viewer !== null) {
            $profileKey = 'profile-audit-view:'.$user->id.':'.$viewer->id.':'.now()->toDateString();
            if (\Illuminate\Support\Facades\Cache::add($profileKey, 1, now()->endOfDay())) {
                app(\App\Services\UserActivity\UserActivityRecorder::class)->record(
                    $viewer,
                    'profile.viewed',
                    'Viewed freelancer profile',
                    $user->name.($user->username ? ' (@'.$user->username.')' : ''),
                    User::class,
                    (int) $user->id,
                    ['target_username' => $user->username],
                    $request,
                );
            }
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

        $reviewTotal = (clone $reviewBase)->count();

        $recentReviews = (clone $reviewBase)
            ->with(['quest:id,title', 'reviewer:id,first_name,name', 'attachments'])
            ->latest('created_at')
            ->limit(4)
            ->get();

        $portfolioPreview = [];
        if ($settings['show_portfolio'] ?? true) {
            $portfolioPreview = Portfolio::query()
                ->where('user_id', $user->id)
                ->where('status', PortfolioStatus::Published)
                ->where('admin_hidden', false)
                ->orderByDesc('published_at')
                ->limit(6)
                ->get()
                ->map(fn (Portfolio $p) => [
                    'slug' => $p->slug,
                    'title' => $p->title,
                    'cover_url' => $p->coverUrl(),
                    'favorites_count' => (int) $p->favorites_count,
                ])
                ->all();
        }

        $followsReady = Schema::hasTable('user_follows');

        $viewerCanFollow = $followsReady
            && $viewer !== null
            && (int) $viewer->id !== (int) $user->id
            && (
                ($viewer->role?->slug === 'client' && $user->role?->slug === 'freelancer')
                || ($viewer->role?->slug === 'freelancer' && $user->role?->slug === 'client')
            );

        $isFollowing = false;
        if ($followsReady && $viewer !== null && (int) $viewer->id !== (int) $user->id) {
            $isFollowing = UserFollow::query()
                ->where('follower_id', $viewer->id)
                ->where('following_id', $user->id)
                ->exists();
        }

        $followersCount = $followsReady
            ? UserFollow::query()->where('following_id', $user->id)->count()
            : 0;

        $followingCount = $followsReady
            ? UserFollow::query()->where('follower_id', $user->id)->count()
            : 0;

        $displayName = $user->first_name
            ? trim($user->first_name.' '.($user->last_name ?? ''))
            : $user->name;
        $verificationEngine = app(VerificationEngineService::class);
        $powerHours = app(PowerHoursService::class);
        $proMembership = app(FreelancerProSubscriptionService::class);
        $completedVerificationTypes = $verificationEngine->completedVerificationTypes($user);

        $profile = [
            'slug' => $user->slug,
            'username' => $user->username,
            'name' => $displayName !== '' ? $displayName : $user->name,
            'avatar_url' => $user->avatar_url,
            'verification_tier' => $user->verification_tier,
            'is_pro' => $proMembership->isPro($user),
            'verification_engine' => [
                'earned_level' => $verificationEngine->storedLevel($user),
                'effective_level' => $verificationEngine->effectiveLevel($user),
                'proposal_limit_minor' => $verificationEngine->freelancerProposalLimitMinor($user),
                'portfolio_verified' => in_array('portfolio_review', $completedVerificationTypes, true),
            ],
            'trust_score' => $user->trust_score,
            'avg_rating' => $user->avg_rating_as_freelancer,
            'rating_count' => $user->ratings_count_as_freelancer,
            'member_since' => $user->created_at?->timezone('Africa/Lagos')->format('M Y'),
            'profession' => ($settings['show_experience'] ?? true) ? $user->profession : null,
            'years_experience' => ($settings['show_experience'] ?? true) ? $user->years_experience : null,
            'power_hours' => ($settings['show_power_hours'] ?? true) ? $powerHours->signalFor($user) : null,
        ];

        if ($settings['show_headline'] ?? true) {
            $profile['headline'] = $user->headline;
        }

        if ($settings['show_bio'] ?? true) {
            $profile['bio'] = $user->bio;
        }

        if ($settings['show_location'] ?? true) {
            $profile['state'] = $user->stateModel?->name;
            $profile['local_government'] = $user->localGovernmentModel?->name;
            $profile['city'] = $user->city;
        }

        if ($settings['show_rates'] ?? true) {
            $profile['hourly_rate_min'] = $user->hourly_rate_min;
            $profile['hourly_rate_max'] = $user->hourly_rate_max;
        }

        if (($settings['show_cac'] ?? true) && $user->freelancerBusinessProfile) {
            $profile['cac'] = [
                'registration_number' => $user->freelancerBusinessProfile->cac_registration_number,
                'status' => $user->freelancerBusinessProfile->cac_verification_status,
                'verified_at' => $user->freelancerBusinessProfile->cac_verified_at?->toIso8601String(),
            ];
        } else {
            $profile['cac'] = null;
        }

        if (($settings['show_phone'] ?? false) && filled($user->phone)) {
            $profile['phone'] = $user->phone;
        }
        if (($settings['show_email'] ?? false) && filled($user->email)) {
            $profile['email'] = $user->email;
        }

        if (($settings['show_credentials'] ?? true) && $user->freelancerCredentials->isNotEmpty()) {
            $profile['credentials'] = $user->freelancerCredentials->map(fn ($c) => [
                'type' => $c->credential_type,
                'title' => $c->title,
                'issuing_authority' => $c->issuing_authority,
                'reference_number' => $c->reference_number,
                'issued_on' => $c->issued_on?->toDateString(),
                'expires_on' => $c->expires_on?->toDateString(),
                'coverage_summary' => $c->coverage_summary,
                'is_verified' => $c->is_verified,
            ])->all();
        } else {
            $profile['credentials'] = [];
        }

        if ($proMembership->isPro($user)) {
            $profile['pro_sections'] = $proMembership->proProfileSectionsFrom($user);
        }

        return Inertia::render('Public/FreelancerProfile', [
            'profile' => $profile,
            'presence' => $this->presenceForPublic($viewer, $user),
            'reviewSnapshot' => [
                'total' => $reviewTotal,
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
            'portfolioPreview' => $portfolioPreview,
            'links' => [
                'reviews_index' => route('freelancers.public.reviews', $user->slug),
                'portfolios_index' => ($settings['show_portfolio'] ?? true)
                    ? route('freelancers.public.portfolios', $user->slug)
                    : null,
            ],
            'social' => [
                'viewer_can_follow' => $viewerCanFollow,
                'is_following' => $isFollowing,
                'followers_count' => $followersCount,
                'following_count' => $followingCount,
            ],
            'is_authenticated' => $viewer !== null,
            'viewer_role_slug' => $viewer?->role?->slug ?? '',
        ]);
    }

    /**
     * @return array{show_indicator: bool, online: bool}
     */
    protected function presenceForPublic(?User $viewer, User $profile): array
    {
        $mins = (int) config('presence.online_within_minutes', 5);
        $online = $profile->last_active_at !== null
            && $profile->last_active_at->greaterThan(now()->subMinutes($mins));

        $viewerHides = (bool) ($viewer?->hide_online_presence ?? false);
        $profileHides = (bool) $profile->hide_online_presence;

        $showIndicator = ! $viewerHides && ! $profileHides && $online;

        return [
            'show_indicator' => $showIndicator,
            'online' => $online,
        ];
    }
}
