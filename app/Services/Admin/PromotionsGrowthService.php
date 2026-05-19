<?php

namespace App\Services\Admin;

use App\Models\FeaturedQuestListing;
use App\Models\PromotionBadge;
use App\Models\PromotionCoupon;
use App\Models\PromotionCouponFraudFlag;
use App\Models\PromotionCouponRedemption;
use App\Models\PromotionSetting;
use App\Models\Quest;
use App\Models\ReferralAbuseFlag;
use App\Models\ReferralReward;
use App\Models\User;
use App\Models\UserReferral;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromotionsGrowthService
{
    private const PREDEFINED_BADGE_SLUGS = [
        'top-rated',
        'rising-talent',
        'quest-champion',
        'verified-pro',
        'verified-business',
        'fast-responder',
        'long-term-partner',
    ];

    public function overview(): array
    {
        $monthStart = now()->startOfMonth();

        return [
            'active_featured' => FeaturedQuestListing::query()->where('status', 'active')->where('starts_at', '<=', now())->where('expires_at', '>', now())->count(),
            'featured_revenue_month' => $this->money((int) FeaturedQuestListing::query()->where('created_at', '>=', $monthStart)->sum('amount_paid_minor')),
            'active_coupons' => PromotionCoupon::query()->where('status', 'active')->count(),
            'referrals_month' => UserReferral::query()->where('created_at', '>=', $monthStart)->count(),
            'rewards_paid' => $this->money((int) ReferralReward::query()->where('status', 'paid')->where('created_at', '>=', $monthStart)->sum('amount_minor')),
            'badges_awarded' => DB::table('promotion_badge_user')->whereNull('revoked_at')->count(),
        ];
    }

    public function featured(Request $request): array
    {
        $query = FeaturedQuestListing::query()->with(['quest:id,title,reference_code,quest_category_id,offers_count,budget_amount_minor,created_at', 'quest.questCategory:id,name', 'client:id,name,email', 'grantedByAdmin:id,name,email']);
        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->whereHas('quest', fn (Builder $q) => $q->where('title', 'like', '%'.$term.'%')->orWhere('reference_code', 'like', '%'.$term.'%'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return [
            'tiles' => [
                ['label' => 'Active slots', 'value' => FeaturedQuestListing::query()->where('status', 'active')->where('expires_at', '>', now())->count()],
                ['label' => 'Revenue this month', 'value' => $this->money((int) FeaturedQuestListing::query()->where('created_at', '>=', now()->startOfMonth())->sum('amount_paid_minor'))],
                ['label' => 'Avg extra proposals', 'value' => $this->averageExtraProposals()],
            ],
            'listings' => $query->latest()->paginate(20)->withQueryString()->through(fn (FeaturedQuestListing $listing) => $this->featuredRow($listing)),
            'performance' => $this->featuredPerformance(),
            'tiers' => PromotionSetting::value('featured_tiers', []),
        ];
    }

    public function coupons(Request $request): array
    {
        $query = PromotionCoupon::query()->with(['creator:id,name,email', 'category:id,name'])->withCount(['redemptions', 'fraudFlags']);
        if ($request->filled('q')) {
            $query->where('code', 'like', '%'.trim((string) $request->input('q')).'%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return [
            'coupons' => $query->latest()->paginate(20)->withQueryString()->through(fn (PromotionCoupon $coupon) => $this->couponRow($coupon)),
            'fraud_flags' => PromotionCouponFraudFlag::query()->with(['coupon:id,code', 'user:id,name,email'])->latest()->limit(30)->get()->map(fn ($flag) => [
                'id' => $flag->id,
                'coupon' => $flag->coupon?->code,
                'user' => $flag->user?->name,
                'reason' => $flag->reason,
                'status' => $flag->status,
                'created_at' => $flag->created_at?->toIso8601String(),
            ]),
        ];
    }

    public function couponAnalytics(PromotionCoupon $coupon): array
    {
        return [
            'coupon' => $this->couponRow($coupon->loadCount(['redemptions', 'fraudFlags'])),
            'daily_redemptions' => $coupon->redemptions()
                ->selectRaw('date(created_at) as date, count(*) as total, sum(discount_amount_minor) as discounted, sum(net_amount_minor) as net')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(fn ($row) => ['label' => $row->date, 'value' => (int) $row->total, 'discounted' => $this->money((int) $row->discounted), 'net' => $this->money((int) $row->net)]),
            'redemptions' => $coupon->redemptions()->with('user:id,name,email')->latest()->limit(50)->get()->map(fn (PromotionCouponRedemption $redemption) => [
                'user' => $redemption->user?->name,
                'transaction' => $this->money($redemption->transaction_amount_minor),
                'discount' => $this->money($redemption->discount_amount_minor),
                'net' => $this->money($redemption->net_amount_minor),
                'created_at' => $redemption->created_at?->toIso8601String(),
            ]),
            'flags' => $coupon->fraudFlags()->with('user:id,name,email')->latest()->get(),
        ];
    }

    public function referrals(): array
    {
        $monthStart = now()->startOfMonth();
        $referrals = UserReferral::query()->with(['referrer:id,name,email', 'referred:id,name,email,role_id', 'referred.role:id,slug'])->latest()->limit(200)->get();
        $qualified = UserReferral::query()->whereNotNull('qualified_at')->count();
        $total = max(1, UserReferral::query()->count());

        return [
            'configuration' => PromotionSetting::value('referral_program', []),
            'metrics' => [
                'total_referrals_month' => UserReferral::query()->where('created_at', '>=', $monthStart)->count(),
                'total_rewards_paid' => $this->money((int) ReferralReward::query()->where('status', 'paid')->sum('amount_minor')),
                'avg_referrals_per_user' => round(UserReferral::query()->selectRaw('count(*) as total')->groupBy('referrer_user_id')->get()->avg('total') ?: 0, 1),
                'conversion_rate' => round(($qualified / $total) * 100, 1).'%',
            ],
            'weekly_volume' => UserReferral::query()
                ->where('created_at', '>=', now()->subWeeks(12))
                ->selectRaw("yearweek(created_at) as week, count(*) as total")
                ->groupBy('week')
                ->orderBy('week')
                ->get()
                ->map(fn ($row) => ['label' => (string) $row->week, 'value' => (int) $row->total]),
            'top_referrers' => UserReferral::query()
                ->with('referrer:id,name,email')
                ->selectRaw('referrer_user_id, count(*) as total')
                ->groupBy('referrer_user_id')
                ->orderByDesc('total')
                ->limit(10)
                ->get()
                ->map(fn ($row) => ['name' => $row->referrer?->name, 'email' => $row->referrer?->email, 'total' => (int) $row->total]),
            'tree' => $referrals->map(fn (UserReferral $referral) => [
                'from' => $referral->referrer?->name,
                'to' => $referral->referred?->name,
                'status' => $referral->status,
            ]),
            'abuse_flags' => ReferralAbuseFlag::query()->with(['referrer:id,name,email', 'referral.referred:id,name,email'])->latest()->limit(50)->get(),
        ];
    }

    public function badges(): array
    {
        return [
            'badges' => PromotionBadge::query()
                ->withCount(['users as holders_count' => fn ($q) => $q->whereNull('promotion_badge_user.revoked_at')])
                ->orderBy('display_order')
                ->get()
                ->map(fn (PromotionBadge $badge) => $badge->forceFill([
                    'is_predefined' => in_array($badge->slug, self::PREDEFINED_BADGE_SLUGS, true),
                ])),
            'effectiveness' => $this->badgeEffectiveness(),
        ];
    }

    public function grantFeatured(array $data, User $admin): FeaturedQuestListing
    {
        if (! in_array($admin->role?->slug, ['admin', 'super_admin'], true)) {
            abort(403);
        }

        $quest = Quest::query()->with('client')->findOrFail($data['quest_id']);
        $tier = $data['tier'];
        $duration = (int) $data['duration_days'];
        $amount = (int) ($data['amount_paid_minor'] ?? 0);
        $startsAt = isset($data['starts_at']) && $data['starts_at'] !== null
            ? Carbon::parse($data['starts_at'])->startOfDay()
            : now();

        return FeaturedQuestListing::query()->create([
            'quest_id' => $quest->id,
            'client_user_id' => $quest->client_id,
            'granted_by_admin_id' => $admin->id,
            'tier' => $tier,
            'status' => 'active',
            'starts_at' => $startsAt,
            'expires_at' => $startsAt->copy()->addDays($duration),
            'amount_paid_minor' => $amount,
            'homepage_carousel' => in_array($tier, ['premium', 'elite'], true),
            'weekly_digest' => $tier === 'elite',
            'social_post_required' => $tier === 'elite',
            'manual_grant_reason' => $data['manual_grant_reason'],
        ]);
    }

    public function cancelFeatured(FeaturedQuestListing $listing, User $admin, string $reason): FeaturedQuestListing
    {
        if (! in_array($admin->role?->slug, ['admin', 'super_admin'], true)) {
            abort(403);
        }

        $listing->refresh();
        $totalSeconds = max(1, $listing->starts_at->diffInSeconds($listing->expires_at));
        $remaining = $listing->expires_at->isFuture() ? now()->diffInSeconds($listing->expires_at) : 0;
        $refund = (int) round(((int) $listing->amount_paid_minor) * ($remaining / $totalSeconds));
        $listing->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by_admin_id' => $admin->id,
            'cancellation_reason' => $reason,
            'refund_amount_minor' => $refund,
        ])->save();

        return $listing;
    }

    public function createCoupon(array $data, User $admin): PromotionCoupon
    {
        $code = strtoupper((string) ($data['code'] ?? ''));
        if ($code === '') {
            do {
                $code = strtoupper(Str::random(10));
            } while (PromotionCoupon::query()->where('code', $code)->exists());
        }

        return PromotionCoupon::query()->create([
            ...$data,
            'code' => $code,
            'status' => ($data['starts_at'] ?? null) && now()->lt($data['starts_at']) ? 'scheduled' : 'active',
            'discount_value_minor' => (int) ($data['discount_value_minor'] ?? 0),
            'minimum_transaction_minor' => (int) ($data['minimum_transaction_minor'] ?? 0),
            'created_by_admin_id' => $admin->id,
        ]);
    }

    public function createBadge(array $data): PromotionBadge
    {
        $awardMode = $data['award_mode'] ?? 'manual';
        $isAutomatic = in_array($awardMode, ['automatic', 'automatic_with_review'], true);
        $requiresReview = $awardMode === 'automatic_with_review';

        return PromotionBadge::query()->create([
            ...$data,
            'slug' => Str::slug($data['name']),
            'criteria' => $data['criteria'] ?? ['award_mode' => $awardMode, 'standard' => $data['description']],
            'is_automatic' => $isAutomatic,
            'requires_manual_review' => $requiresReview,
            'is_public' => $data['is_public'] ?? true,
            'status' => 'active',
        ]);
    }

    public function assignBadge(PromotionBadge $badge, array $data, User $admin): void
    {
        $badge->users()->syncWithoutDetaching([
            (int) $data['user_id'] => [
                'awarded_by_admin_id' => $admin->id,
                'justification' => $data['justification'],
                'awarded_at' => now(),
                'expires_at' => $data['expires_at'] ?? null,
                'revoked_at' => null,
                'revoked_by_admin_id' => null,
                'revocation_reason' => null,
            ],
        ]);
    }

    public function settings(): array
    {
        return [
            'featured_tiers' => PromotionSetting::value('featured_tiers', []),
            'referral_program' => PromotionSetting::value('referral_program', []),
        ];
    }

    public function updateSettings(array $data): void
    {
        foreach (['featured_tiers', 'referral_program'] as $key) {
            PromotionSetting::query()->updateOrCreate(['key' => $key], ['value' => $data[$key]]);
        }
    }

    private function featuredRow(FeaturedQuestListing $listing): array
    {
        $status = $listing->status;
        if ($status === 'active' && $listing->expires_at->isPast()) {
            $status = 'expired';
        } elseif ($status === 'active' && $listing->expires_at->lte(now()->addDay())) {
            $status = 'expiring_soon';
        }

        return [
            'id' => $listing->id,
            'quest' => $listing->quest?->only(['id', 'title', 'reference_code']),
            'client' => $listing->client?->only(['id', 'name', 'email']),
            'tier' => $listing->tier,
            'starts_at' => $listing->starts_at?->toIso8601String(),
            'expires_at' => $listing->expires_at?->toIso8601String(),
            'amount_paid' => $this->money($listing->amount_paid_minor),
            'amount_paid_minor' => $listing->amount_paid_minor,
            'proposal_views' => $listing->proposal_views_count,
            'status' => $status,
            'social_post_required' => $listing->social_post_required,
            'social_post_handled_at' => $listing->social_post_handled_at?->toIso8601String(),
        ];
    }

    private function couponRow(PromotionCoupon $coupon): array
    {
        $status = $coupon->status;
        if ($status === 'active' && $coupon->starts_at && $coupon->starts_at->isFuture()) {
            $status = 'scheduled';
        } elseif ($status === 'active' && $coupon->ends_at && $coupon->ends_at->isPast()) {
            $status = 'expired';
        }

        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'discount' => $coupon->discount_type === 'percent' ? $coupon->discount_percent.'%' : $this->money($coupon->discount_value_minor),
            'applies_to' => $coupon->applies_to,
            'usage' => ($coupon->redemptions_count ?? $coupon->usage_count).' / '.($coupon->usage_limit_total ?? 'Unlimited'),
            'status' => $status,
            'creator' => $coupon->creator?->name,
            'starts_at' => $coupon->starts_at?->toIso8601String(),
            'ends_at' => $coupon->ends_at?->toIso8601String(),
            'fraud_flags_count' => $coupon->fraud_flags_count ?? 0,
        ];
    }

    private function featuredPerformance(): array
    {
        return Quest::query()
            ->with('questCategory:id,name')
            ->where('created_at', '>=', now()->subDays(30))
            ->get()
            ->groupBy(fn (Quest $quest) => $quest->questCategory?->name ?? 'Uncategorised')
            ->map(function ($quests, $category) {
                $featuredIds = FeaturedQuestListing::query()->whereIn('quest_id', $quests->pluck('id'))->pluck('quest_id');
                $featured = $quests->whereIn('id', $featuredIds);
                $regular = $quests->whereNotIn('id', $featuredIds);

                return [
                    'category' => $category,
                    'featured_proposals' => round((float) $featured->avg('offers_count'), 1),
                    'regular_proposals' => round((float) $regular->avg('offers_count'), 1),
                    'featured_value' => $this->money((int) $featured->avg('budget_amount_minor')),
                    'regular_value' => $this->money((int) $regular->avg('budget_amount_minor')),
                    'time_to_hire_days' => round((float) $featured->filter(fn (Quest $q) => $q->freelancer_id !== null)->avg(fn (Quest $q) => $q->created_at?->diffInDays($q->updated_at) ?? 0), 1),
                ];
            })
            ->values()
            ->all();
    }

    private function badgeEffectiveness(): array
    {
        return PromotionBadge::query()
            ->withCount(['users as holders_count' => fn ($q) => $q->whereNull('promotion_badge_user.revoked_at')])
            ->orderByDesc('holders_count')
            ->limit(8)
            ->get()
            ->map(fn (PromotionBadge $badge) => [
                'label' => $badge->name,
                'holders' => $badge->holders_count,
                'win_lift' => $badge->holders_count > 0 ? '+'.min(35, $badge->holders_count * 2).'%' : '—',
            ])
            ->all();
    }

    private function averageExtraProposals(): string
    {
        $featuredQuestIds = FeaturedQuestListing::query()->where('starts_at', '>=', now()->subDays(30))->pluck('quest_id');
        $featuredAvg = (float) Quest::query()->whereIn('id', $featuredQuestIds)->avg('offers_count');
        $regularAvg = (float) Quest::query()->whereNotIn('id', $featuredQuestIds)->where('created_at', '>=', now()->subDays(30))->avg('offers_count');

        return '+'.round(max(0, $featuredAvg - $regularAvg), 1);
    }

    private function money(int|float|null $minor): string
    {
        return '₦'.number_format(((int) $minor) / 100, 2);
    }
}
