<?php

use App\Http\Controllers\Admin\AdminActivityLogController;
use App\Http\Controllers\Admin\AdminContentModerationController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDisputesController;
use App\Http\Controllers\Admin\AdminEngagementPolicyController;
use App\Http\Controllers\Admin\AdminFinancialControlController;
use App\Http\Controllers\Admin\AdminKycCentreController;
use App\Http\Controllers\Admin\AdminLiveActivityController;
use App\Http\Controllers\Admin\AdminManagementController;
use App\Http\Controllers\Admin\AdminPromotionsGrowthController;
use App\Http\Controllers\Admin\AdminUserActivityController;
use App\Http\Controllers\Admin\AdminReportsController;
use App\Http\Controllers\Admin\AdminQuestsController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminStaffController;
use App\Http\Controllers\Admin\AdminUsersController;
use Illuminate\Support\Facades\Route;

Route::get('/', AdminDashboardController::class)->name('dashboard');
Route::get('/exports/dashboard.csv', [AdminDashboardController::class, 'export'])->name('dashboard.export');

Route::get('/reports', [AdminReportsController::class, 'index'])->name('reports.index');
Route::get('/reports/export', [AdminReportsController::class, 'export'])->name('reports.export');
Route::post('/reports/preview', [AdminReportsController::class, 'preview'])->name('reports.preview');
Route::post('/reports/saved', [AdminReportsController::class, 'store'])->name('reports.saved.store');
Route::post('/reports/saved/{report}/run', [AdminReportsController::class, 'runSaved'])->name('reports.saved.run');
Route::post('/reports/saved/{report}/email-now', [AdminReportsController::class, 'runNowAndEmail'])->name('reports.saved.email-now');
Route::post('/reports/exports', [AdminReportsController::class, 'exportReport'])->name('reports.exports.store');

Route::get('/financial', [AdminFinancialControlController::class, 'index'])->name('financial.index');
Route::get('/financial/summary', [AdminFinancialControlController::class, 'summary'])->name('financial.summary');
Route::get('/financial/escrows/{quest:id}/ledger', [AdminFinancialControlController::class, 'escrowLedger'])->name('financial.escrows.ledger');
Route::post('/financial/escrows/{quest:id}/action', [AdminFinancialControlController::class, 'escrowAction'])
    ->middleware('throttle:20,1')
    ->name('financial.escrows.action');

Route::get('/kyc', [AdminKycCentreController::class, 'index'])->name('kyc.index');
Route::get('/kyc/cases/{case}', [AdminKycCentreController::class, 'show'])->name('kyc.cases.show');
Route::post('/kyc/cases/{case}/reveal', [AdminKycCentreController::class, 'reveal'])
    ->middleware('throttle:20,1')
    ->name('kyc.cases.reveal');
Route::post('/kyc/cases/{case}/decision', [AdminKycCentreController::class, 'decide'])
    ->middleware('throttle:30,1')
    ->name('kyc.cases.decision');
Route::get('/kyc/documents/{document}', [AdminKycCentreController::class, 'document'])
    ->middleware('throttle:60,1')
    ->name('kyc.documents.show');
Route::patch('/kyc/settings', [AdminKycCentreController::class, 'updateSettings'])
    ->middleware('throttle:20,1')
    ->name('kyc.settings.update');

Route::get('/promotions', [AdminPromotionsGrowthController::class, 'index'])->name('promotions.index');
Route::post('/promotions/featured', [AdminPromotionsGrowthController::class, 'grantFeatured'])
    ->middleware('throttle:30,1')
    ->name('promotions.featured.store');
Route::post('/promotions/featured/{listing}/cancel', [AdminPromotionsGrowthController::class, 'cancelFeatured'])
    ->middleware('throttle:30,1')
    ->name('promotions.featured.cancel');
Route::post('/promotions/coupons', [AdminPromotionsGrowthController::class, 'storeCoupon'])
    ->middleware('throttle:30,1')
    ->name('promotions.coupons.store');
Route::patch('/promotions/coupons/{coupon}/pause', [AdminPromotionsGrowthController::class, 'pauseCoupon'])
    ->middleware('throttle:30,1')
    ->name('promotions.coupons.pause');
Route::get('/promotions/coupons/{coupon}/analytics', [AdminPromotionsGrowthController::class, 'couponAnalytics'])
    ->name('promotions.coupons.analytics');
Route::post('/promotions/badges', [AdminPromotionsGrowthController::class, 'storeBadge'])
    ->middleware('throttle:20,1')
    ->name('promotions.badges.store');
Route::post('/promotions/badges/{badge}/assign', [AdminPromotionsGrowthController::class, 'assignBadge'])
    ->middleware('throttle:30,1')
    ->name('promotions.badges.assign');
Route::post('/promotions/badges/{badge}/users/{user}/revoke', [AdminPromotionsGrowthController::class, 'revokeBadge'])
    ->middleware('throttle:30,1')
    ->name('promotions.badges.revoke');
Route::patch('/promotions/settings', [AdminPromotionsGrowthController::class, 'updateSettings'])
    ->middleware('throttle:20,1')
    ->name('promotions.settings.update');
Route::post('/promotions/referrals/rewards/{reward}/void', [AdminPromotionsGrowthController::class, 'voidReward'])
    ->middleware('throttle:30,1')
    ->name('promotions.referrals.rewards.void');
Route::post('/promotions/referrals/referrers/{user}/block', [AdminPromotionsGrowthController::class, 'blockReferrer'])
    ->middleware('throttle:30,1')
    ->name('promotions.referrals.referrers.block');

Route::get('/live-activity', [AdminLiveActivityController::class, 'index'])->name('live-activity.index');
Route::get('/live-activity/events', [AdminLiveActivityController::class, 'events'])->name('live-activity.events');
Route::get('/live-activity/summary', [AdminLiveActivityController::class, 'summary'])->name('live-activity.summary');
Route::get('/live-activity/widget', [AdminLiveActivityController::class, 'widget'])->name('live-activity.widget');
Route::get('/live-activity/entity', [AdminLiveActivityController::class, 'entity'])->name('live-activity.entity');
Route::post('/live-activity/{event}/action', [AdminLiveActivityController::class, 'action'])->name('live-activity.action');

Route::get('/content-moderation', [AdminContentModerationController::class, 'index'])->name('content-moderation.index');
Route::get('/content-moderation/cases/{case}', [AdminContentModerationController::class, 'show'])->name('content-moderation.cases.show');
Route::post('/content-moderation/cases/{case}/decision', [AdminContentModerationController::class, 'decide'])
    ->middleware('throttle:40,1')
    ->name('content-moderation.cases.decision');
Route::post('/content-moderation/keywords', [AdminContentModerationController::class, 'storeKeyword'])
    ->middleware('throttle:30,1')
    ->name('content-moderation.keywords.store');
Route::patch('/content-moderation/keywords/{keyword}', [AdminContentModerationController::class, 'updateKeyword'])
    ->middleware('throttle:30,1')
    ->name('content-moderation.keywords.update');
Route::delete('/content-moderation/keywords/{keyword}', [AdminContentModerationController::class, 'destroyKeyword'])
    ->middleware('throttle:30,1')
    ->name('content-moderation.keywords.destroy');
Route::patch('/content-moderation/settings', [AdminContentModerationController::class, 'updateSettings'])
    ->middleware('throttle:20,1')
    ->name('content-moderation.settings.update');

Route::get('/management', [AdminManagementController::class, 'index'])->name('management.index');
Route::post('/management/{resource}', [AdminManagementController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('management.store');
Route::patch('/management/{resource}/{record}', [AdminManagementController::class, 'update'])
    ->middleware('throttle:30,1')
    ->name('management.update');
Route::delete('/management/{resource}/{record}', [AdminManagementController::class, 'destroy'])
    ->middleware('throttle:20,1')
    ->name('management.destroy');
Route::post('/management/users/{user}/suspend', [AdminManagementController::class, 'suspend'])
    ->middleware('throttle:20,1')
    ->name('management.users.suspend');
Route::get('/management/users/{user}/activity', [AdminUserActivityController::class, 'show'])
    ->name('management.users.activity');
Route::get('/management/conversation_threads/{thread}', [AdminManagementController::class, 'showConversationThread'])
    ->name('management.conversation_threads.show');
Route::post('/management/conversation_threads/{thread}/visibility', [AdminManagementController::class, 'updateConversationThreadVisibility'])
    ->middleware('throttle:20,1')
    ->name('management.conversation_threads.visibility');
Route::get('/management/{resource}/{record}', [AdminManagementController::class, 'show'])
    ->name('management.show');

Route::get('/engagement-policy', [AdminEngagementPolicyController::class, 'show'])->name('engagement-policy');
Route::get('/engagement-policy/export.md', [AdminEngagementPolicyController::class, 'export'])->name('engagement-policy.export');

Route::get('/quests', [AdminQuestsController::class, 'index'])->name('quests.index');
Route::get('/quests/export', [AdminQuestsController::class, 'export'])->name('quests.export');
Route::post('/quests/import', [AdminQuestsController::class, 'import'])
    ->middleware('throttle:8,1')
    ->name('quests.import');

Route::get('/users', [AdminUsersController::class, 'index'])->name('users.index');
Route::get('/users/export', [AdminUsersController::class, 'export'])->name('users.export');
Route::post('/users/segments', [AdminUsersController::class, 'saveSegment'])
    ->middleware('throttle:30,1')
    ->name('users.segments.store');
Route::post('/users/bulk', [AdminUsersController::class, 'bulk'])
    ->middleware('throttle:20,1')
    ->name('users.bulk');
Route::post('/users/impersonation/stop', [AdminUsersController::class, 'stopImpersonating'])
    ->middleware('throttle:20,1')
    ->name('users.impersonation.stop');
Route::get('/users/{user}/profile', [AdminUsersController::class, 'profile'])->name('users.profile');
Route::post('/users/{user}/notes', [AdminUsersController::class, 'storeNote'])
    ->middleware('throttle:30,1')
    ->name('users.notes.store');
Route::post('/users/{user}/sanctions', [AdminUsersController::class, 'sanction'])
    ->middleware('throttle:20,1')
    ->name('users.sanctions.store');
Route::post('/users/{user}/sanctions/{sanction}/reverse', [AdminUsersController::class, 'reverseSanction'])
    ->middleware('throttle:20,1')
    ->name('users.sanctions.reverse');
Route::post('/users/{user}/impersonate', [AdminUsersController::class, 'impersonate'])
    ->middleware('throttle:10,1')
    ->name('users.impersonate');

Route::get('/disputes', [AdminDisputesController::class, 'index'])->name('disputes.index');
Route::get('/disputes/export', [AdminDisputesController::class, 'export'])->name('disputes.export');

Route::get('/activity', [AdminActivityLogController::class, 'index'])->name('activity.index');
Route::get('/activity/export', [AdminActivityLogController::class, 'export'])->name('activity.export');

Route::get('/staff', [AdminStaffController::class, 'index'])->name('staff.index');
Route::post('/staff/invite', [AdminStaffController::class, 'invite'])
    ->middleware('throttle:12,1')
    ->name('staff.invite');
Route::post('/staff', [AdminStaffController::class, 'store'])
    ->middleware('throttle:12,1')
    ->name('staff.store');
Route::get('/staff/export', [AdminStaffController::class, 'export'])->name('staff.export');
Route::post('/staff/import', [AdminStaffController::class, 'import'])
    ->middleware('throttle:6,1')
    ->name('staff.import');

Route::get('/settings', [AdminSettingsController::class, 'show'])->name('settings.index');
Route::get('/settings/export', [AdminSettingsController::class, 'export'])->name('settings.export');
