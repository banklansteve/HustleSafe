<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignPromotionBadgeRequest;
use App\Http\Requests\Admin\CancelFeaturedQuestListingRequest;
use App\Http\Requests\Admin\StoreFeaturedQuestListingRequest;
use App\Http\Requests\Admin\StorePromotionBadgeRequest;
use App\Http\Requests\Admin\StorePromotionCouponRequest;
use App\Http\Requests\Admin\UpdatePromotionSettingsRequest;
use App\Models\FeaturedQuestListing;
use App\Models\PromotionBadge;
use App\Models\PromotionCoupon;
use App\Models\PromotionSetting;
use App\Models\ReferralAbuseFlag;
use App\Models\ReferralReward;
use App\Models\User;
use App\Services\Admin\PromotionsGrowthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPromotionsGrowthController extends Controller
{
    public function __construct(private readonly PromotionsGrowthService $growth) {}

    public function index(Request $request): Response
    {
        $section = (string) $request->query('section', 'featured');
        if (! in_array($section, ['featured', 'coupons', 'referrals', 'badges', 'settings'], true)) {
            $section = 'featured';
        }

        return Inertia::render('Admin/Promotions/Index', [
            'section' => $section,
            'overview' => fn () => $this->growth->overview(),
            'featured' => fn () => $section === 'featured' ? $this->growth->featured($request) : null,
            'coupons' => fn () => $section === 'coupons' ? $this->growth->coupons($request) : null,
            'referrals' => fn () => $section === 'referrals' ? $this->growth->referrals() : null,
            'badges' => fn () => $section === 'badges' ? $this->growth->badges() : null,
            'settings' => fn () => $section === 'settings' ? $this->growth->settings() : null,
            'filters' => $request->only(['q', 'status', 'per_page']),
        ]);
    }

    public function grantFeatured(StoreFeaturedQuestListingRequest $request): RedirectResponse
    {
        $this->growth->grantFeatured($request->validated(), $request->user());

        return back()->with('success', 'Featured listing granted.');
    }

    public function cancelFeatured(CancelFeaturedQuestListingRequest $request, FeaturedQuestListing $listing): RedirectResponse
    {
        $updated = $this->growth->cancelFeatured($listing, $request->user(), (string) $request->validated('reason'));

        return back()->with('success', 'Featured listing cancelled. Refund due: ₦'.number_format($updated->refund_amount_minor / 100, 2));
    }

    public function storeCoupon(StorePromotionCouponRequest $request): RedirectResponse
    {
        $this->growth->createCoupon($request->validated(), $request->user());

        return back()->with('success', 'Coupon created.');
    }

    public function pauseCoupon(PromotionCoupon $coupon): RedirectResponse
    {
        $coupon->update(['status' => 'paused']);

        return back()->with('success', 'Coupon paused.');
    }

    public function couponAnalytics(PromotionCoupon $coupon): JsonResponse
    {
        return response()->json($this->growth->couponAnalytics($coupon));
    }

    public function storeBadge(StorePromotionBadgeRequest $request): RedirectResponse
    {
        $this->growth->createBadge($request->validated());

        return back()->with('success', 'Badge created.');
    }

    public function assignBadge(AssignPromotionBadgeRequest $request, PromotionBadge $badge): RedirectResponse
    {
        $this->growth->assignBadge($badge, $request->validated(), $request->user());

        return back()->with('success', 'Badge awarded.');
    }

    public function revokeBadge(Request $request, PromotionBadge $badge, User $user): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $badge->users()->updateExistingPivot($user->id, [
            'revoked_at' => now(),
            'revoked_by_admin_id' => $request->user()->id,
            'revocation_reason' => $request->input('reason'),
        ]);

        return back()->with('success', 'Badge revoked.');
    }

    public function updateSettings(UpdatePromotionSettingsRequest $request): RedirectResponse
    {
        $this->growth->updateSettings($request->validated());

        return back()->with('success', 'Promotion settings saved.');
    }

    public function voidReward(Request $request, ReferralReward $reward): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $reward->update(['status' => 'void', 'metadata' => array_merge($reward->metadata ?? [], ['void_reason' => $request->input('reason'), 'voided_by' => $request->user()->id])]);

        return back()->with('success', 'Referral reward voided.');
    }

    public function blockReferrer(Request $request, User $user): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $user->update(['referral_program_blocked_at' => now()]);
        ReferralAbuseFlag::query()->create([
            'referrer_user_id' => $user->id,
            'reason' => 'admin_blocked_referrer',
            'status' => 'open',
            'evidence' => ['reason' => $request->input('reason'), 'admin_id' => $request->user()->id],
        ]);

        return back()->with('success', 'Referrer blocked from programme.');
    }
}
