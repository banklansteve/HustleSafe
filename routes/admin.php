<?php

use App\Http\Controllers\Admin\AdminActivityLogController;
use App\Http\Controllers\Admin\AdminCategoryManagementController;
use App\Http\Controllers\Admin\AdminCommandController;
use App\Http\Controllers\Admin\AdminComplianceCentreController;
use App\Http\Controllers\Admin\AdminContentManagementController;
use App\Http\Controllers\Admin\AdminContentModerationController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDocumentationController;
use App\Http\Controllers\Admin\AdminDirectMessageController;
use App\Http\Controllers\Admin\AdminDisputesController;
use App\Http\Controllers\Admin\AdminEngagementPolicyController;
use App\Http\Controllers\Admin\AdminEmailBroadcastController;
use App\Http\Controllers\Admin\AdminFinancialControlController;
use App\Http\Controllers\Admin\AdminPlatformFeeLedgerController;
use App\Http\Controllers\Admin\AdminQuestCompletionEventsController;
use App\Http\Controllers\Admin\AdminQuestReleaseController;
use App\Http\Controllers\Admin\AdminUserVerificationReviewController;
use App\Http\Controllers\Shared\UserVerificationDocumentController;
use App\Http\Controllers\Admin\AdminContractReceiptController;
use App\Http\Controllers\Admin\AdminPaymentsEscrowController;
use App\Http\Controllers\Admin\AdminFraudRiskController;
use App\Http\Controllers\Admin\AdminInsightsController;
use App\Http\Controllers\Admin\AdminIntelligenceController;
use App\Http\Controllers\Admin\AdminKycCentreController;
use App\Http\Controllers\Admin\AdminLiveActivityController;
use App\Http\Controllers\Admin\AdminManagementController;
use App\Http\Controllers\Admin\AdminMessagesController;
use App\Http\Controllers\Admin\AdminStaffActivitySummaryController;
use App\Http\Controllers\Admin\AdminNotificationCentreController;
use App\Http\Controllers\Admin\AdminPromotionsGrowthController;
use App\Http\Controllers\Admin\AdminPortfolioReviewController;
use App\Http\Controllers\Admin\AdminProposalsController;
use App\Http\Controllers\Admin\AdminUserActivityController;
use App\Http\Controllers\Admin\AdminReportsController;
use App\Http\Controllers\Admin\AdminQuestsController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminStaffController;
use App\Http\Controllers\Admin\AdminStaffActivityDigestController;
use App\Http\Controllers\Admin\AdminCustomerSupportController;
use App\Http\Controllers\Admin\AdminSupportTicketController;
use App\Http\Controllers\Admin\AdminTaskController;
use App\Http\Controllers\Admin\AdminTreasuryController;
use App\Http\Controllers\Admin\AdminUsersController;
use App\Http\Controllers\Admin\AdminVerificationEngineController;
use App\Http\Controllers\Admin\Api\AdminUserProfileTabController;
use Illuminate\Support\Facades\Route;

Route::get('/', AdminDashboardController::class)->name('dashboard');
Route::get('/exports/dashboard.csv', [AdminDashboardController::class, 'export'])->name('dashboard.export');
Route::get('/documentation/dashboard-guide/{topic?}', AdminDocumentationController::class)->name('documentation.guide');

Route::get('/insights', AdminInsightsController::class)->name('insights.index');
Route::get('/command/search', [AdminCommandController::class, 'search'])->name('command.search');
Route::get('/alerts', [AdminNotificationCentreController::class, 'index'])->name('alerts.index');
Route::get('/alerts/{notification}/open', [AdminNotificationCentreController::class, 'open'])->name('alerts.open');
Route::get('/api/alerts/unread-count', [AdminNotificationCentreController::class, 'unreadCount'])->name('api.alerts.unread-count');

Route::get('/messages', [AdminMessagesController::class, 'index'])->name('messages.index');
Route::get('/api/staff/{user}/activity-summary', AdminStaffActivitySummaryController::class)->name('api.staff-activity.summary');
Route::get('/api/messenger/bootstrap', [AdminDirectMessageController::class, 'bootstrap'])->name('api.messenger.bootstrap');
Route::get('/api/messenger/unread-count', [AdminDirectMessageController::class, 'unreadCount'])->name('api.messenger.unread-count');
Route::get('/api/messenger/conversations', [AdminDirectMessageController::class, 'conversations'])->name('api.messenger.conversations');
Route::post('/api/messenger/open/{recipient}', [AdminDirectMessageController::class, 'open'])->name('api.messenger.open');
Route::get('/api/messenger/conversations/{conversation}/messages', [AdminDirectMessageController::class, 'messages'])->name('api.messenger.messages');
Route::post('/api/messenger/conversations/{conversation}/messages', [AdminDirectMessageController::class, 'send'])->middleware('throttle:120,1')->name('api.messenger.send');
Route::post('/api/messenger/messages/{message}/delivered', [AdminDirectMessageController::class, 'delivered'])->middleware('throttle:180,1')->name('api.messenger.delivered');
Route::post('/api/messenger/conversations/{conversation}/read', [AdminDirectMessageController::class, 'read'])->middleware('throttle:120,1')->name('api.messenger.read');
Route::post('/api/messenger/conversations/{conversation}/typing', [AdminDirectMessageController::class, 'typing'])->middleware('throttle:180,1')->name('api.messenger.typing');
Route::get('/api/messenger/gifs', [AdminDirectMessageController::class, 'gifSearch'])->name('api.messenger.gifs');
Route::post('/alerts/{notification}/read', [AdminNotificationCentreController::class, 'markRead'])->name('alerts.read');
Route::post('/alerts/{notification}/action', [AdminNotificationCentreController::class, 'action'])->name('alerts.action');
Route::post('/alerts/read-all', [AdminNotificationCentreController::class, 'markAllRead'])->name('alerts.read-all');
Route::get('/customer-support', [AdminCustomerSupportController::class, 'index'])->name('customer-support.index');
Route::get('/customer-support/performance', [AdminCustomerSupportController::class, 'performance'])->name('customer-support.performance');
Route::get('/api/customer-support/performance/{admin}/feedback', [AdminCustomerSupportController::class, 'performanceFeedback'])->name('api.customer-support.performance-feedback');
Route::get('/api/customer-support/queue', [AdminCustomerSupportController::class, 'queue'])->name('api.customer-support.queue');
Route::get('/api/customer-support/history', [AdminCustomerSupportController::class, 'history'])->name('api.customer-support.history');
Route::get('/api/customer-support/unread-count', [AdminCustomerSupportController::class, 'unreadCount'])->name('api.customer-support.unread-count');
Route::get('/api/customer-support/tickets/{ticket}', [AdminCustomerSupportController::class, 'open'])->name('api.customer-support.open');
Route::get('/api/customer-support/tickets/{ticket}/messages', [AdminCustomerSupportController::class, 'messages'])->name('api.customer-support.messages');
Route::post('/api/customer-support/tickets/{ticket}/messages', [AdminCustomerSupportController::class, 'send'])->middleware('throttle:120,1')->name('api.customer-support.send');
Route::get('/api/customer-support/tickets/{ticket}/typing', [AdminCustomerSupportController::class, 'typingState'])->name('api.customer-support.typing-state');
Route::post('/api/customer-support/tickets/{ticket}/typing', [AdminCustomerSupportController::class, 'typing'])->middleware('throttle:180,1')->name('api.customer-support.typing');
Route::post('/api/customer-support/tickets/{ticket}/read', [AdminCustomerSupportController::class, 'read'])->middleware('throttle:120,1')->name('api.customer-support.read');
Route::post('/api/customer-support/reconcile-notifications', [AdminCustomerSupportController::class, 'reconcileNotifications'])->middleware('throttle:120,1')->name('api.customer-support.reconcile-notifications');
Route::post('/api/customer-support/tickets/{ticket}/end', [AdminCustomerSupportController::class, 'end'])->middleware('throttle:60,1')->name('api.customer-support.end');
Route::post('/api/customer-support/tickets/{ticket}/reassign', [AdminCustomerSupportController::class, 'reassign'])->middleware('throttle:30,1')->name('api.customer-support.reassign');
Route::post('/api/customer-support/tickets/{ticket}/messages/{message}/react', [AdminCustomerSupportController::class, 'react'])->middleware('throttle:120,1')->name('api.customer-support.react');
Route::get('/api/customer-support/search', [AdminCustomerSupportController::class, 'search'])->name('api.customer-support.search');
Route::get('/api/customer-support/users/{user}/context', [AdminCustomerSupportController::class, 'userContext'])->name('api.customer-support.user-context');
Route::get('/api/customer-support/gifs', [AdminCustomerSupportController::class, 'gifSearch'])->name('api.customer-support.gifs');
Route::get('/support-tickets', [AdminSupportTicketController::class, 'index'])->name('support-tickets.index');
Route::patch('/support-tickets/{ticket}/status', [AdminSupportTicketController::class, 'updateTicketStatus'])->middleware('throttle:60,1')->name('support-tickets.status');
Route::post('/support-tickets/bulk-messages/{bulkMessage}/approve', [AdminSupportTicketController::class, 'approveBulkMessage'])->middleware('throttle:20,1')->name('support-tickets.bulk-messages.approve');
Route::get('/tasks', [AdminTaskController::class, 'index'])->name('tasks.index');
Route::post('/tasks', [AdminTaskController::class, 'store'])->middleware('throttle:30,1')->name('tasks.store');
Route::patch('/tasks/{task}/status', [AdminTaskController::class, 'status'])->middleware('throttle:60,1')->name('tasks.status');
Route::get('/intelligence', AdminIntelligenceController::class)->name('intelligence.index');
Route::get('/treasury', AdminTreasuryController::class)->name('treasury.index');
Route::get('/fraud-risk', AdminFraudRiskController::class)->name('fraud.index');
Route::get('/compliance', [AdminComplianceCentreController::class, 'index'])->name('compliance.index');
Route::post('/compliance/requests', [AdminComplianceCentreController::class, 'store'])->middleware('throttle:20,1')->name('compliance.requests.store');
Route::get('/reports', [AdminReportsController::class, 'index'])->name('reports.index');
Route::get('/reports/export', [AdminReportsController::class, 'export'])->name('reports.export');
Route::post('/reports/preview', [AdminReportsController::class, 'preview'])->name('reports.preview');
Route::post('/reports/saved', [AdminReportsController::class, 'store'])->name('reports.saved.store');
Route::post('/reports/saved/{report}/run', [AdminReportsController::class, 'runSaved'])->name('reports.saved.run');
Route::post('/reports/saved/{report}/email-now', [AdminReportsController::class, 'runNowAndEmail'])->name('reports.saved.email-now');
Route::post('/reports/exports', [AdminReportsController::class, 'exportReport'])->name('reports.exports.store');

Route::get('/payments-escrow', [AdminPaymentsEscrowController::class, 'index'])->name('payments-escrow.index');
Route::post('/payments-escrow/escrows/{escrow}/release', [AdminPaymentsEscrowController::class, 'forceRelease'])
    ->middleware('throttle:20,1')
    ->name('payments-escrow.escrows.release');
Route::post('/payments-escrow/escrows/{escrow}/refund', [AdminPaymentsEscrowController::class, 'forceRefund'])
    ->middleware('throttle:20,1')
    ->name('payments-escrow.escrows.refund');
Route::post('/payments-escrow/users/{user}/wallet/lock', [AdminPaymentsEscrowController::class, 'lockWallet'])
    ->middleware('throttle:20,1')
    ->name('payments-escrow.wallet.lock');
Route::post('/payments-escrow/users/{user}/wallet/unlock', [AdminPaymentsEscrowController::class, 'unlockWallet'])
    ->middleware('throttle:20,1')
    ->name('payments-escrow.wallet.unlock');

Route::get('/financial', [AdminFinancialControlController::class, 'index'])->name('financial.index');
Route::get('/financial/summary', [AdminFinancialControlController::class, 'summary'])->name('financial.summary');
Route::get('/financial/escrows/{quest:id}/ledger', [AdminFinancialControlController::class, 'escrowLedger'])->name('financial.escrows.ledger');
Route::post('/financial/escrows/{quest:id}/action', [AdminFinancialControlController::class, 'escrowAction'])
    ->middleware('throttle:20,1')
    ->name('financial.escrows.action');
Route::get('/api/platform-fees', [AdminPlatformFeeLedgerController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('api.platform-fees.index');
Route::get('/api/platform-fees/export', [AdminPlatformFeeLedgerController::class, 'export'])
    ->middleware('throttle:20,1')
    ->name('api.platform-fees.export');
Route::get('/quest-completion-events', [AdminQuestCompletionEventsController::class, 'index'])
    ->name('quest-completion-events.index');
Route::get('/user-verifications/{verification}/document', [UserVerificationDocumentController::class, '__invoke'])
    ->middleware('throttle:120,1')
    ->name('user-verifications.document');
Route::post('/user-verifications/{verification}/decide', [AdminUserVerificationReviewController::class, 'decide'])
    ->middleware('throttle:60,1')
    ->name('user-verifications.decide');
Route::post('/quests/{quest:id}/release/authorize', [AdminQuestReleaseController::class, 'authorizeRelease'])
    ->middleware('throttle:30,1')
    ->name('quests.release.authorize');
Route::post('/quests/{quest:id}/release/hold', [AdminQuestReleaseController::class, 'holdRelease'])
    ->middleware('throttle:30,1')
    ->name('quests.release.hold');
Route::post('/quests/{quest:id}/release/lift-hold', [AdminQuestReleaseController::class, 'liftHold'])
    ->middleware('throttle:30,1')
    ->name('quests.release.lift-hold');
Route::get('/contracts/{quest:id}/receipt', [AdminContractReceiptController::class, 'show'])
    ->name('contracts.receipt');

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

Route::get('/verification-engine', [AdminVerificationEngineController::class, 'index'])->name('verification-engine.index');
Route::patch('/verification-engine/types', [AdminVerificationEngineController::class, 'updateTypes'])
    ->middleware('throttle:20,1')
    ->name('verification-engine.types.update');
Route::patch('/verification-engine/limits', [AdminVerificationEngineController::class, 'updateLimits'])
    ->middleware('throttle:20,1')
    ->name('verification-engine.limits.update');
Route::patch('/verification-engine/safeguards', [AdminVerificationEngineController::class, 'updateSafeguards'])
    ->middleware('throttle:20,1')
    ->name('verification-engine.safeguards.update');
Route::post('/verification-engine/verifications/{verification}/decision', [AdminVerificationEngineController::class, 'decide'])
    ->middleware('throttle:40,1')
    ->name('verification-engine.verifications.decision');
Route::post('/verification-engine/verifications/bulk-decision', [AdminVerificationEngineController::class, 'bulkDecide'])
    ->middleware('throttle:20,1')
    ->name('verification-engine.verifications.bulk-decision');
Route::post('/verification-engine/anomalies/{flag}/action', [AdminVerificationEngineController::class, 'anomalyAction'])
    ->middleware('throttle:40,1')
    ->name('verification-engine.anomalies.action');
Route::get('/verification-engine/users/search', [AdminVerificationEngineController::class, 'searchUsers'])
    ->middleware('throttle:60,1')
    ->name('verification-engine.users.search');
Route::get('/verification-engine/users/{user}/timeline', [AdminVerificationEngineController::class, 'accountTimeline'])
    ->middleware('throttle:60,1')
    ->name('verification-engine.users.timeline');
Route::post('/verification-engine/users/{user}/level-override', [AdminVerificationEngineController::class, 'overrideLevel'])
    ->middleware('throttle:20,1')
    ->name('verification-engine.users.level-override');
Route::post('/verification-engine/users/{user}/limit-override', [AdminVerificationEngineController::class, 'overrideLimits'])
    ->middleware('throttle:20,1')
    ->name('verification-engine.users.limit-override');

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

Route::get('/categories', [AdminCategoryManagementController::class, 'index'])->name('categories.index');
Route::post('/categories', [AdminCategoryManagementController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('categories.store');
Route::patch('/categories/{category}', [AdminCategoryManagementController::class, 'update'])
    ->middleware('throttle:30,1')
    ->name('categories.update');
Route::post('/categories/reorder', [AdminCategoryManagementController::class, 'reorder'])
    ->middleware('throttle:60,1')
    ->name('categories.reorder');
Route::post('/categories/reorder/undo', [AdminCategoryManagementController::class, 'undoReorder'])
    ->middleware('throttle:30,1')
    ->name('categories.reorder.undo');
Route::post('/categories/{category}/hide', [AdminCategoryManagementController::class, 'hide'])
    ->middleware('throttle:30,1')
    ->name('categories.hide');
Route::post('/categories/{category}/archive', [AdminCategoryManagementController::class, 'archive'])
    ->middleware('throttle:30,1')
    ->name('categories.archive');
Route::post('/categories/{category}/restore', [AdminCategoryManagementController::class, 'restore'])
    ->middleware('throttle:30,1')
    ->name('categories.restore');
Route::get('/categories/{category}/performance', [AdminCategoryManagementController::class, 'performance'])
    ->name('categories.performance');
Route::post('/categories/bulk', [AdminCategoryManagementController::class, 'bulk'])
    ->middleware('throttle:20,1')
    ->name('categories.bulk');
Route::post('/categories/import', [AdminCategoryManagementController::class, 'import'])
    ->middleware('throttle:8,1')
    ->name('categories.import');
Route::get('/categories/import/template.csv', [AdminCategoryManagementController::class, 'template'])
    ->name('categories.import.template');
Route::post('/categories/export', [AdminCategoryManagementController::class, 'export'])
    ->middleware('throttle:10,1')
    ->name('categories.export');
Route::get('/categories/unique/check', [AdminCategoryManagementController::class, 'unique'])
    ->middleware('throttle:120,1')
    ->name('categories.unique');

Route::get('/content', [AdminContentManagementController::class, 'index'])->name('content.index');
Route::get('/content/email-templates/{template}', [AdminContentManagementController::class, 'showTemplate'])->name('content.email-templates.show');
Route::patch('/content/email-templates/{template}', [AdminContentManagementController::class, 'updateTemplate'])
    ->middleware('throttle:40,1')
    ->name('content.email-templates.update');
Route::post('/content/email-templates/{template}/versions/{version}/restore', [AdminContentManagementController::class, 'restoreTemplate'])
    ->middleware('throttle:20,1')
    ->name('content.email-templates.versions.restore');
Route::post('/content/email-templates/{template}/test', [AdminContentManagementController::class, 'testTemplate'])
    ->middleware('throttle:12,1')
    ->name('content.email-templates.test');
Route::post('/content/announcements', [AdminContentManagementController::class, 'storeAnnouncement'])
    ->middleware('throttle:30,1')
    ->name('content.announcements.store');
Route::patch('/content/announcements/{banner}', [AdminContentManagementController::class, 'updateAnnouncement'])
    ->middleware('throttle:30,1')
    ->name('content.announcements.update');
Route::delete('/content/announcements/{banner}', [AdminContentManagementController::class, 'archiveAnnouncement'])
    ->middleware('throttle:20,1')
    ->name('content.announcements.archive');
Route::post('/content/help/sections', [AdminContentManagementController::class, 'storeHelpSection'])
    ->middleware('throttle:20,1')
    ->name('content.help.sections.store');
Route::post('/content/help/faqs', [AdminContentManagementController::class, 'storeFaq'])
    ->middleware('throttle:30,1')
    ->name('content.help.faqs.store');
Route::patch('/content/help/faqs/{faq}', [AdminContentManagementController::class, 'updateFaq'])
    ->middleware('throttle:30,1')
    ->name('content.help.faqs.update');
Route::delete('/content/help/faqs/{faq}', [AdminContentManagementController::class, 'archiveFaq'])
    ->middleware('throttle:20,1')
    ->name('content.help.faqs.archive');
Route::post('/content/help/faqs/{faq}/versions/{version}/restore', [AdminContentManagementController::class, 'restoreFaq'])
    ->middleware('throttle:20,1')
    ->name('content.help.faqs.versions.restore');

Route::get('/communications/email-broadcasts', [AdminEmailBroadcastController::class, 'index'])->name('communications.email-broadcasts.index');
Route::post('/communications/email-broadcasts/audience', [AdminEmailBroadcastController::class, 'audience'])
    ->middleware('throttle:60,1')
    ->name('communications.email-broadcasts.audience');
Route::post('/communications/email-broadcasts/send', [AdminEmailBroadcastController::class, 'send'])
    ->middleware('throttle:12,1')
    ->name('communications.email-broadcasts.send');
Route::post('/communications/email-broadcasts/test', [AdminEmailBroadcastController::class, 'test'])
    ->middleware('throttle:12,1')
    ->name('communications.email-broadcasts.test');
Route::post('/communications/email-broadcasts/templates', [AdminEmailBroadcastController::class, 'storeTemplate'])
    ->middleware('throttle:20,1')
    ->name('communications.email-broadcasts.templates.store');
Route::patch('/communications/email-broadcasts/templates/{template}', [AdminEmailBroadcastController::class, 'updateTemplate'])
    ->middleware('throttle:20,1')
    ->name('communications.email-broadcasts.templates.update');
Route::delete('/communications/email-broadcasts/templates/{template}', [AdminEmailBroadcastController::class, 'destroyTemplate'])
    ->middleware('throttle:20,1')
    ->name('communications.email-broadcasts.templates.destroy');

Route::get('/live-activity', [AdminLiveActivityController::class, 'index'])->name('live-activity.index');
Route::get('/live-activity/events', [AdminLiveActivityController::class, 'events'])->name('live-activity.events');
Route::get('/live-activity/summary', [AdminLiveActivityController::class, 'summary'])->name('live-activity.summary');
Route::get('/live-activity/widget', [AdminLiveActivityController::class, 'widget'])->name('live-activity.widget');
Route::get('/live-activity/entity', [AdminLiveActivityController::class, 'entity'])->name('live-activity.entity');
Route::post('/live-activity/{event}/action', [AdminLiveActivityController::class, 'action'])->name('live-activity.action');

Route::get('/portfolio-review', [AdminPortfolioReviewController::class, 'index'])->name('portfolio-review.index');
Route::get('/portfolio-review/{portfolio}', [AdminPortfolioReviewController::class, 'show'])->name('portfolio-review.show');
Route::patch('/portfolio-review/{portfolio}', [AdminPortfolioReviewController::class, 'update'])
    ->middleware('throttle:40,1')
    ->name('portfolio-review.update');

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
Route::get('/quests/{quest}/detail', [AdminQuestsController::class, 'detail'])->name('quests.detail');
Route::patch('/quests/{quest}', [AdminQuestsController::class, 'updateQuest'])
    ->middleware('throttle:30,1')
    ->name('quests.update');
Route::delete('/quests/{quest}', [AdminQuestsController::class, 'destroyQuest'])
    ->middleware('throttle:12,1')
    ->name('quests.destroy');
Route::patch('/quests/{quest}/status', [AdminQuestsController::class, 'status'])
    ->middleware('throttle:30,1')
    ->name('quests.status');
Route::patch('/quests/{quest}/admin-status', [AdminQuestsController::class, 'adminStatus'])
    ->middleware('throttle:30,1')
    ->name('quests.admin-status');
Route::post('/quests/{quest}/notices', [AdminQuestsController::class, 'notice'])
    ->middleware('throttle:30,1')
    ->name('quests.notices.store');
Route::post('/quests/{quest}/notes', [AdminQuestsController::class, 'note'])
    ->middleware('throttle:30,1')
    ->name('quests.notes.store');
Route::post('/quests/{quest}/flags', [AdminQuestsController::class, 'flag'])
    ->middleware('throttle:30,1')
    ->name('quests.flags.store');
Route::post('/quests/{quest}/flags/{flag}/resolve', [AdminQuestsController::class, 'resolveFlag'])
    ->middleware('throttle:30,1')
    ->name('quests.flags.resolve');
Route::post('/quests/{quest}/boost', [AdminQuestsController::class, 'boost'])
    ->middleware('throttle:20,1')
    ->name('quests.boost');
Route::post('/quests/import', [AdminQuestsController::class, 'import'])
    ->middleware('throttle:8,1')
    ->name('quests.import');

Route::get('/proposals', [AdminProposalsController::class, 'index'])->name('proposals.index');
Route::get('/proposals/export', [AdminProposalsController::class, 'export'])->name('proposals.export');
Route::get('/proposals/{proposal}/detail', [AdminProposalsController::class, 'detail'])->name('proposals.detail');
Route::patch('/proposals/{proposal}', [AdminProposalsController::class, 'updateProposal'])
    ->middleware('throttle:30,1')
    ->name('proposals.update');
Route::delete('/proposals/{proposal}', [AdminProposalsController::class, 'destroyProposal'])
    ->middleware('throttle:8,1')
    ->name('proposals.destroy');
Route::patch('/proposals/{proposal}/admin-status', [AdminProposalsController::class, 'adminStatus'])
    ->middleware('throttle:30,1')
    ->name('proposals.admin-status');
Route::post('/proposals/{proposal}/flags', [AdminProposalsController::class, 'flag'])
    ->middleware('throttle:30,1')
    ->name('proposals.flags.store');
Route::post('/proposals/{proposal}/flags/{flag}/resolve', [AdminProposalsController::class, 'resolveFlag'])
    ->middleware('throttle:30,1')
    ->name('proposals.flags.resolve');
Route::post('/proposals/{proposal}/notices', [AdminProposalsController::class, 'notice'])
    ->middleware('throttle:30,1')
    ->name('proposals.notices.store');
Route::post('/proposals/{proposal}/notes', [AdminProposalsController::class, 'note'])
    ->middleware('throttle:30,1')
    ->name('proposals.notes.store');
Route::post('/proposals/bulk', [AdminProposalsController::class, 'bulk'])
    ->middleware('throttle:12,1')
    ->name('proposals.bulk');

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
Route::get('/api/users/{user}/profile-tab', AdminUserProfileTabController::class)->name('api.users.profile-tab');
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
Route::get('/activity/digest', AdminStaffActivityDigestController::class)->name('activity.digest');
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

Route::get('/api/maintenance/status', [\App\Http\Controllers\Admin\AdminMaintenanceController::class, 'status'])->name('api.maintenance.status');
Route::post('/api/maintenance/on', [\App\Http\Controllers\Admin\AdminMaintenanceController::class, 'on'])->middleware('throttle:20,1')->name('api.maintenance.on');
Route::match(['get', 'post'], '/api/maintenance/off', [\App\Http\Controllers\Admin\AdminMaintenanceController::class, 'off'])->middleware('throttle:20,1')->name('api.maintenance.off');
Route::patch('/api/maintenance', [\App\Http\Controllers\Admin\AdminMaintenanceController::class, 'update'])->middleware('throttle:20,1')->name('api.maintenance.update');
Route::get('/settings', [AdminSettingsController::class, 'show'])->name('settings.index');
Route::patch('/settings/{section}', [AdminSettingsController::class, 'update'])
    ->middleware('throttle:20,1')
    ->name('settings.update');
Route::get('/settings/export', [AdminSettingsController::class, 'export'])->name('settings.export');

Route::get('/team-chat', [\App\Http\Controllers\Operations\OperationsTeamChatController::class, 'index'])->name('team-chat.index');
Route::get('/api/team-chat/bootstrap', [\App\Http\Controllers\Operations\OperationsTeamChatController::class, 'bootstrap'])->name('api.team-chat.bootstrap');
Route::get('/api/team-chat/rooms/{room}/messages', [\App\Http\Controllers\Operations\OperationsTeamChatController::class, 'messages'])->name('api.team-chat.messages');
Route::post('/api/team-chat/rooms/{room}/messages', [\App\Http\Controllers\Operations\OperationsTeamChatController::class, 'send'])->middleware('throttle:120,1')->name('api.team-chat.send');
Route::post('/api/team-chat/messages/{message}/react', [\App\Http\Controllers\Operations\OperationsTeamChatController::class, 'react'])->middleware('throttle:120,1')->name('api.team-chat.react');
Route::post('/api/team-chat/rooms/{room}/messages/{message}/pin', [\App\Http\Controllers\Operations\OperationsTeamChatController::class, 'pin'])->middleware('throttle:30,1')->name('api.team-chat.pin');
Route::post('/api/team-chat/rooms/{room}/read', [\App\Http\Controllers\Operations\OperationsTeamChatController::class, 'read'])->middleware('throttle:120,1')->name('api.team-chat.read');
Route::post('/api/team-chat/rooms/{room}/typing', [\App\Http\Controllers\Operations\OperationsTeamChatController::class, 'typing'])->middleware('throttle:180,1')->name('api.team-chat.typing');
Route::get('/api/team-chat/rooms/{room}/search', [\App\Http\Controllers\Operations\OperationsTeamChatController::class, 'search'])->name('api.team-chat.search');
Route::get('/api/team-chat/presence', [\App\Http\Controllers\Operations\OperationsTeamChatController::class, 'presence'])->name('api.team-chat.presence');
Route::delete('/api/team-chat/messages/{message}', [\App\Http\Controllers\Operations\OperationsTeamChatController::class, 'remove'])->middleware('throttle:30,1')->name('api.team-chat.remove');
Route::get('/api/team-chat/gifs', [\App\Http\Controllers\Shared\ChatGifController::class, 'index'])->name('api.team-chat.gifs');

Route::get('/knowledge-base', [\App\Http\Controllers\Admin\AdminKnowledgeBaseController::class, 'index'])->name('knowledge-base.index');
Route::post('/knowledge-base', [\App\Http\Controllers\Admin\AdminKnowledgeBaseController::class, 'store'])->middleware('throttle:20,1')->name('knowledge-base.store');
Route::patch('/knowledge-base/{article}', [\App\Http\Controllers\Admin\AdminKnowledgeBaseController::class, 'update'])->middleware('throttle:20,1')->name('knowledge-base.update');
