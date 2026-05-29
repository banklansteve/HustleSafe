<?php

use App\Http\Controllers\Operations\OperationsCommunicationsController;
use App\Http\Controllers\Operations\OperationsDashboardController;
use App\Http\Controllers\Operations\OperationsDisputesController;
use App\Http\Controllers\Operations\OperationsEscalationController;
use App\Http\Controllers\Operations\OperationsModerationController;
use App\Http\Controllers\Operations\OperationsNotificationsController;
use App\Http\Controllers\Operations\OperationsOnboardingController;
use App\Http\Controllers\Operations\OperationsOnboardingQualityController;
use App\Http\Controllers\Operations\OperationsPatrolController;
use App\Http\Controllers\Operations\OperationsPaymentMonitoringController;
use App\Http\Controllers\Operations\OperationsPayoutExceptionsController;
use App\Http\Controllers\Operations\OperationsQualityController;
use App\Http\Controllers\Operations\OperationsTrustController;
use App\Http\Controllers\Operations\OperationsConversationMonitoringController;
use App\Http\Controllers\Operations\OperationsPaymentsController;
use App\Http\Controllers\Operations\OperationsPortfoliosController;
use App\Http\Controllers\Operations\OperationsProposalsController;
use App\Http\Controllers\Operations\OperationsQuestsController;
use App\Http\Controllers\Operations\OperationsReviewsController;
use App\Http\Controllers\Admin\AdminCustomerSupportController;
use App\Http\Controllers\Operations\OperationsSupportHubController;
use App\Http\Controllers\Operations\OperationsTasksController;
use App\Http\Controllers\Operations\OperationsUsersController;
use App\Http\Controllers\Operations\OperationsVerificationsController;
use App\Http\Controllers\Operations\OperationsReviewIntegrityController;
use App\Http\Controllers\Operations\OperationsEscrowAnomaliesController;
use App\Http\Controllers\Operations\OperationsAccountController;
use App\Http\Controllers\Operations\OperationsHrExportController;
use App\Http\Controllers\Operations\OperationsHrSelfServiceController;
use App\Http\Controllers\Operations\OperationsSanctionAppealsController;
use App\Http\Controllers\Operations\OperationsCommunicationsViewerController;
use App\Http\Controllers\Operations\OperationsKnowledgeBaseController;
use App\Http\Controllers\Operations\OperationsCategoryHealthController;
use App\Http\Controllers\Operations\OperationsBadgeRequestsController;
use App\Http\Controllers\Admin\AdminDirectMessageController;
use App\Http\Controllers\Operations\OperationsTeamChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', OperationsDashboardController::class)->name('dashboard');
Route::get('/account', [OperationsAccountController::class, 'show'])->name('account.index');
Route::patch('/account/details', [OperationsAccountController::class, 'updateDetails'])->name('account.details');
Route::patch('/account/visibility', [OperationsAccountController::class, 'updateVisibility'])->name('account.visibility');
Route::post('/account/avatar', [OperationsAccountController::class, 'updateAvatar'])->middleware('throttle:20,1')->name('account.avatar');
Route::post('/account/leave-requests', [OperationsAccountController::class, 'storeLeaveRequest'])->middleware('throttle:20,1')->name('account.leave-requests.store');
Route::get('/account/payslips/{payslip}/download', [OperationsAccountController::class, 'downloadPayslip'])->name('account.payslips.download');
Route::get('/account/payslips/download', [OperationsAccountController::class, 'downloadPayslipByPeriod'])->name('account.payslips.download-by-period');
Route::get('/hr', [OperationsHrSelfServiceController::class, 'index'])->name('hr.index');
Route::post('/hr/leave-requests', [OperationsHrSelfServiceController::class, 'storeLeaveRequest'])->middleware('throttle:20,1')->name('hr.leave-requests.store');
Route::get('/hr/exports/performance.pdf', [OperationsHrExportController::class, 'performanceReport'])->name('hr.exports.performance');

Route::get('/notifications', [OperationsNotificationsController::class, 'index'])->name('notifications.index');
Route::get('/notifications/{notification}/open', [OperationsNotificationsController::class, 'open'])->name('notifications.open');
Route::get('/api/notifications', [OperationsNotificationsController::class, 'listing'])->name('api.notifications.listing');
Route::get('/api/notifications/unread-count', [OperationsNotificationsController::class, 'unreadCount'])->name('api.notifications.unread-count');
Route::get('/api/notifications/preferences', [OperationsNotificationsController::class, 'preferences'])->name('api.notifications.preferences');
Route::patch('/api/notifications/preferences', [OperationsNotificationsController::class, 'updatePreferences'])->middleware('throttle:30,1')->name('api.notifications.preferences.update');
Route::patch('/api/notifications/{notification}/read', [OperationsNotificationsController::class, 'markRead'])->middleware('throttle:120,1')->name('api.notifications.read');
Route::patch('/api/notifications/{notification}/actioned', [OperationsNotificationsController::class, 'markActioned'])->middleware('throttle:120,1')->name('api.notifications.actioned');

Route::get('/trust', [OperationsTrustController::class, 'index'])->name('trust.index');
Route::get('/api/trust/risk-queue', [OperationsTrustController::class, 'riskQueue'])->name('api.trust.risk-queue');
Route::get('/api/trust/watchlist', [OperationsTrustController::class, 'watchlist'])->name('api.trust.watchlist');
Route::get('/api/trust/feed', [OperationsTrustController::class, 'feed'])->name('api.trust.feed');
Route::get('/api/trust/clusters', [OperationsTrustController::class, 'clusters'])->name('api.trust.clusters');
Route::get('/api/trust/users/{user}', [OperationsTrustController::class, 'userRisk'])->name('api.trust.users.show');
Route::get('/api/trust/users/{user}/network', [OperationsTrustController::class, 'networkGraph'])->name('api.trust.users.network');
Route::get('/api/trust/users/{user}/network-notes', [OperationsTrustController::class, 'networkNotes'])->name('api.trust.users.network-notes');
Route::post('/api/trust/watchlist', [OperationsTrustController::class, 'storeWatchlist'])->middleware('throttle:60,1')->name('api.trust.watchlist.store');
Route::get('/api/trust/watchlist/{item}', [OperationsTrustController::class, 'watchlistDetail'])->name('api.trust.watchlist.detail');
Route::delete('/api/trust/watchlist/{item}', [OperationsTrustController::class, 'destroyWatchlist'])->middleware('throttle:60,1')->name('api.trust.watchlist.destroy');

Route::get('/conversation-monitoring', [OperationsConversationMonitoringController::class, 'index'])->name('conversation-monitoring.index');
Route::get('/api/conversation-monitoring/summary', [OperationsConversationMonitoringController::class, 'summary'])->name('api.conversation-monitoring.summary');
Route::get('/api/conversation-monitoring/queue', [OperationsConversationMonitoringController::class, 'moderationQueue'])->name('api.conversation-monitoring.queue');
Route::get('/api/conversation-monitoring/systematic', [OperationsConversationMonitoringController::class, 'systematicQueue'])->name('api.conversation-monitoring.systematic');
Route::get('/api/conversation-monitoring/reviews/{review}', [OperationsConversationMonitoringController::class, 'threadDetail'])->name('api.conversation-monitoring.reviews.show');
Route::get('/api/conversation-monitoring/systematic/{escalation}', [OperationsConversationMonitoringController::class, 'systematicDetail'])->name('api.conversation-monitoring.systematic.show');
Route::post('/api/conversation-monitoring/reviews/{review}/dismiss', [OperationsConversationMonitoringController::class, 'dismiss'])->middleware('throttle:30,1')->name('api.conversation-monitoring.reviews.dismiss');
Route::post('/api/conversation-monitoring/reviews/{review}/warn', [OperationsConversationMonitoringController::class, 'warn'])->middleware('throttle:30,1')->name('api.conversation-monitoring.reviews.warn');
Route::post('/api/conversation-monitoring/reviews/{review}/escalate', [OperationsConversationMonitoringController::class, 'escalate'])->middleware('throttle:30,1')->name('api.conversation-monitoring.reviews.escalate');
Route::post('/api/conversation-monitoring/reviews/{review}/flag-risk', [OperationsConversationMonitoringController::class, 'flagRisk'])->middleware('throttle:30,1')->name('api.conversation-monitoring.reviews.flag-risk');
Route::post('/api/conversation-monitoring/systematic/{escalation}/dismiss', [OperationsConversationMonitoringController::class, 'dismissSystematic'])->middleware('throttle:30,1')->name('api.conversation-monitoring.systematic.dismiss');

Route::get('/quality', [OperationsQualityController::class, 'index'])->name('quality.index');
Route::get('/api/quality', [OperationsQualityController::class, 'listing'])->name('api.quality.listing');
Route::get('/api/quality/freelancers/{freelancer}', [OperationsQualityController::class, 'detail'])->name('api.quality.detail');
Route::post('/api/quality/freelancers/{freelancer}/contact', [OperationsQualityController::class, 'contact'])->middleware('throttle:30,1')->name('api.quality.contact');
Route::post('/api/quality/freelancers/{freelancer}/warning', [OperationsQualityController::class, 'warning'])->middleware('throttle:30,1')->name('api.quality.warning');
Route::post('/api/quality/freelancers/{freelancer}/restrict', [OperationsQualityController::class, 'restrict'])->middleware('throttle:30,1')->name('api.quality.restrict');
Route::post('/api/quality/freelancers/{freelancer}/refer', [OperationsQualityController::class, 'refer'])->middleware('throttle:30,1')->name('api.quality.refer');

Route::get('/payout-exceptions', [OperationsPayoutExceptionsController::class, 'index'])->name('payout-exceptions.index');
Route::get('/api/payout-exceptions', [OperationsPayoutExceptionsController::class, 'listing'])->name('api.payout-exceptions.listing');
Route::get('/api/payout-exceptions/{exception}', [OperationsPayoutExceptionsController::class, 'detail'])->name('api.payout-exceptions.detail');
Route::post('/api/payout-exceptions', [OperationsPayoutExceptionsController::class, 'store'])->middleware('throttle:30,1')->name('api.payout-exceptions.store');
Route::patch('/api/payout-exceptions/{exception}/resolve', [OperationsPayoutExceptionsController::class, 'resolve'])->middleware('throttle:30,1')->name('api.payout-exceptions.resolve');

Route::get('/patrol', [OperationsPatrolController::class, 'index'])->name('patrol.index');
Route::get('/api/patrol/sessions', [OperationsPatrolController::class, 'sessions'])->name('api.patrol.sessions');
Route::get('/api/patrol/categories', [OperationsPatrolController::class, 'categories'])->name('api.patrol.categories');
Route::post('/api/patrol/sessions', [OperationsPatrolController::class, 'start'])->middleware('throttle:20,1')->name('api.patrol.sessions.start');
Route::get('/api/patrol/sessions/{session}', [OperationsPatrolController::class, 'sessionDetail'])->name('api.patrol.sessions.detail');
Route::post('/api/patrol/items/{item}/decide', [OperationsPatrolController::class, 'decide'])->middleware('throttle:120,1')->name('api.patrol.items.decide');

Route::get('/onboarding', [OperationsOnboardingController::class, 'index'])->name('onboarding.index');
Route::get('/api/onboarding', [OperationsOnboardingController::class, 'listing'])->name('api.onboarding.listing');
Route::get('/api/onboarding/records/{record}', [OperationsOnboardingController::class, 'detail'])->name('api.onboarding.detail');
Route::post('/api/onboarding/records/{record}/outreach', [OperationsOnboardingController::class, 'outreach'])->middleware('throttle:30,1')->name('api.onboarding.outreach');
Route::post('/api/onboarding/records/{record}/resolve', [OperationsOnboardingController::class, 'resolve'])->middleware('throttle:30,1')->name('api.onboarding.resolve');
Route::post('/api/onboarding/records/{record}/ticket', [OperationsOnboardingController::class, 'createTicket'])->middleware('throttle:30,1')->name('api.onboarding.ticket');

Route::get('/onboarding-quality', [OperationsOnboardingQualityController::class, 'index'])->name('onboarding-quality.index');
Route::get('/onboarding-quality/flagged-profiles', [OperationsOnboardingQualityController::class, 'flaggedProfiles'])->name('onboarding-quality.flagged');
Route::get('/api/onboarding-quality', [OperationsOnboardingQualityController::class, 'listing'])->name('api.onboarding-quality.listing');
Route::get('/api/onboarding-quality/flagged-profiles', [OperationsOnboardingQualityController::class, 'flaggedListing'])->name('api.onboarding-quality.flagged');
Route::get('/api/onboarding-quality/reviews/{review}', [OperationsOnboardingQualityController::class, 'detail'])->name('api.onboarding-quality.detail');
Route::post('/api/onboarding-quality/reviews/{review}/actions', [OperationsOnboardingQualityController::class, 'action'])->middleware('throttle:60,1')->name('api.onboarding-quality.action');
Route::get('/exports/dashboard-metrics.csv', [OperationsDashboardController::class, 'export'])->name('dashboard.export');
Route::post('/escalations', OperationsEscalationController::class)->middleware('throttle:20,1')->name('escalations.store');

Route::get('/moderation', [OperationsModerationController::class, 'index'])->name('moderation.index');
Route::get('/api/moderation/quests', [OperationsModerationController::class, 'questListing'])->name('api.moderation.quests');
Route::get('/api/moderation/proposals', [OperationsModerationController::class, 'proposalListing'])->name('api.moderation.proposals');
Route::get('/api/moderation/quests/{quest}', [OperationsModerationController::class, 'questDetail'])->name('api.moderation.quests.detail');
Route::get('/api/moderation/proposals/{proposal}', [OperationsModerationController::class, 'proposalDetail'])->name('api.moderation.proposals.detail');
Route::patch('/api/moderation/quests/{quest}/admin-status', [OperationsModerationController::class, 'questAdminStatus'])->middleware('throttle:60,1')->name('api.moderation.quests.admin-status');
Route::post('/api/moderation/quests/{quest}/notices', [OperationsModerationController::class, 'questNotice'])->middleware('throttle:60,1')->name('api.moderation.quests.notices');
Route::post('/api/moderation/quests/{quest}/notes', [OperationsModerationController::class, 'questNote'])->middleware('throttle:60,1')->name('api.moderation.quests.notes');
Route::post('/api/moderation/quests/{quest}/flags', [OperationsModerationController::class, 'questFlag'])->middleware('throttle:60,1')->name('api.moderation.quests.flags');
Route::patch('/api/moderation/quests/{quest}', [OperationsModerationController::class, 'questUpdate'])->middleware('throttle:30,1')->name('api.moderation.quests.update');
Route::delete('/api/moderation/quests/{quest}/files/{file}', [OperationsModerationController::class, 'questRemoveFile'])->middleware('throttle:60,1')->name('api.moderation.quests.files.destroy');
Route::post('/api/moderation/quests/{quest}/contact', [OperationsModerationController::class, 'questContact'])->middleware('throttle:30,1')->name('api.moderation.quests.contact');
Route::post('/api/moderation/proposals/{proposal}/contact', [OperationsModerationController::class, 'proposalContact'])->middleware('throttle:30,1')->name('api.moderation.proposals.contact');
Route::patch('/api/moderation/proposals/{proposal}/admin-status', [OperationsModerationController::class, 'proposalAdminStatus'])->middleware('throttle:60,1')->name('api.moderation.proposals.admin-status');
Route::post('/api/moderation/proposals/{proposal}/notices', [OperationsModerationController::class, 'proposalNotice'])->middleware('throttle:60,1')->name('api.moderation.proposals.notices');
Route::post('/api/moderation/proposals/{proposal}/notes', [OperationsModerationController::class, 'proposalNote'])->middleware('throttle:60,1')->name('api.moderation.proposals.notes');
Route::post('/api/moderation/proposals/{proposal}/flags', [OperationsModerationController::class, 'proposalFlag'])->middleware('throttle:60,1')->name('api.moderation.proposals.flags');
Route::delete('/api/moderation/proposals/{proposal}', [OperationsModerationController::class, 'proposalRemove'])->middleware('throttle:30,1')->name('api.moderation.proposals.remove');

Route::redirect('/quests', '/operations/moderation?module=quests')->name('quests.index');
Route::get('/quests/export', [OperationsQuestsController::class, 'export'])->name('quests.export');
Route::redirect('/proposals', '/operations/moderation?module=proposals')->name('proposals.index');

Route::get('/users', [OperationsUsersController::class, 'index'])->name('users.index');
Route::get('/users/export', [OperationsUsersController::class, 'export'])->name('users.export');
Route::get('/api/users', [OperationsUsersController::class, 'listing'])->name('api.users.listing');
Route::get('/api/users/{user}/profile', [OperationsUsersController::class, 'profile'])->name('api.users.profile');
Route::post('/api/users/{user}/notes', [OperationsUsersController::class, 'storeNote'])->middleware('throttle:60,1')->name('api.users.notes');
Route::post('/api/users/{user}/warnings', [OperationsUsersController::class, 'warning'])->middleware('throttle:30,1')->name('api.users.warnings');
Route::post('/api/users/{user}/suspend', [OperationsUsersController::class, 'suspend'])->middleware('throttle:30,1')->name('api.users.suspend');
Route::post('/api/users/{user}/unsuspend', [OperationsUsersController::class, 'unsuspend'])->middleware('throttle:30,1')->name('api.users.unsuspend');
Route::post('/api/users/{user}/flag', [OperationsUsersController::class, 'flag'])->middleware('throttle:30,1')->name('api.users.flag');
Route::patch('/api/users/{user}/tags', [OperationsUsersController::class, 'syncTags'])->middleware('throttle:60,1')->name('api.users.tags');
Route::post('/api/users/{user}/message', [OperationsUsersController::class, 'message'])->middleware('throttle:30,1')->name('api.users.message');
Route::patch('/users/{user}/suspension', [OperationsUsersController::class, 'updateSuspension'])
    ->middleware('throttle:60,1')
    ->name('users.suspension.update');

Route::get('/support', [OperationsSupportHubController::class, 'index'])->name('support.index');
Route::get('/api/support/search', [OperationsSupportHubController::class, 'search'])->middleware('throttle:120,1')->name('api.support.search');
Route::get('/api/support/tickets', [OperationsSupportHubController::class, 'tickets'])->name('api.support.tickets');
Route::get('/api/support/chats', [OperationsSupportHubController::class, 'chats'])->name('api.support.chats');
Route::get('/api/support/disputes', [OperationsSupportHubController::class, 'disputes'])->name('api.support.disputes');
Route::post('/support/bulk-messages', [OperationsSupportHubController::class, 'storeBulkMessage'])->middleware('throttle:10,1')->name('support.bulk-messages.store');
Route::post('/support/tickets', [OperationsSupportHubController::class, 'storeTicket'])->middleware('throttle:30,1')->name('support.tickets.store');
Route::patch('/support/tickets/{ticket}/status', [OperationsSupportHubController::class, 'updateTicketStatus'])->middleware('throttle:60,1')->name('support.tickets.status');
Route::get('/api/support/unassigned-chats', [OperationsSupportHubController::class, 'unassignedChats'])->name('api.support.unassigned-chats');
Route::post('/api/support/chats/{assignment}/claim', [OperationsSupportHubController::class, 'claimChat'])->middleware('throttle:60,1')->name('api.support.chats.claim');
Route::get('/api/support/chats/{assignment}', [OperationsSupportHubController::class, 'chatDetail'])->name('api.support.chats.detail');
Route::post('/api/support/chats/{assignment}/reply', [OperationsSupportHubController::class, 'chatReply'])->middleware('throttle:120,1')->name('api.support.chats.reply');
Route::get('/api/support/users/{user}/context', [OperationsSupportHubController::class, 'userContext'])->name('api.support.user-context');
Route::post('/api/support/users/{user}/email', [OperationsSupportHubController::class, 'panelEmail'])->middleware('throttle:30,1')->name('api.support.panel-email');
Route::get('/api/support/tickets/{ticket}', [OperationsSupportHubController::class, 'ticketDetail'])->name('api.support.tickets.detail');

Route::redirect('/communications', '/operations/support')->name('communications.index');
Route::post('/communications/bulk-messages', [OperationsCommunicationsController::class, 'storeBulkMessage'])->middleware('throttle:10,1')->name('communications.bulk-messages.store');
Route::post('/communications/tickets', [OperationsCommunicationsController::class, 'storeTicket'])->middleware('throttle:30,1')->name('communications.tickets.store');
Route::patch('/communications/tickets/{ticket}/status', [OperationsCommunicationsController::class, 'updateTicketStatus'])->middleware('throttle:60,1')->name('communications.tickets.status');

Route::get('/disputes', [OperationsDisputesController::class, 'index'])->name('disputes.index');
Route::get('/disputes/export', [OperationsDisputesController::class, 'export'])->name('disputes.export');
Route::get('/api/disputes', [OperationsDisputesController::class, 'listing'])->name('api.disputes.listing');
Route::get('/api/disputes/{dispute}', [OperationsDisputesController::class, 'detail'])->name('api.disputes.detail');
Route::post('/api/disputes/{dispute}/claim', [OperationsDisputesController::class, 'claim'])->middleware('throttle:60,1')->name('api.disputes.claim');
Route::post('/api/disputes/{dispute}/notes', [OperationsDisputesController::class, 'internalNote'])->middleware('throttle:60,1')->name('api.disputes.notes');
Route::post('/api/disputes/{dispute}/notices', [OperationsDisputesController::class, 'notice'])->middleware('throttle:60,1')->name('api.disputes.notices');
Route::post('/api/disputes/{dispute}/contact', [OperationsDisputesController::class, 'contact'])->middleware('throttle:60,1')->name('api.disputes.contact');
Route::post('/api/disputes/{dispute}/evidence', [OperationsDisputesController::class, 'requestEvidence'])->middleware('throttle:30,1')->name('api.disputes.evidence');
Route::patch('/api/disputes/{dispute}/tier', [OperationsDisputesController::class, 'tier'])->middleware('throttle:60,1')->name('api.disputes.tier');
Route::post('/api/disputes/{dispute}/ruling', [OperationsDisputesController::class, 'ruling'])->middleware('throttle:20,1')->name('api.disputes.ruling');

Route::get('/payment-monitoring', [OperationsPaymentMonitoringController::class, 'index'])->name('payment-monitoring.index');
Route::get('/api/payment-monitoring', [OperationsPaymentMonitoringController::class, 'listing'])->name('api.payment-monitoring.listing');
Route::post('/api/payment-monitoring/flags', [OperationsPaymentMonitoringController::class, 'flag'])->middleware('throttle:60,1')->name('api.payment-monitoring.flag');

Route::get('/payments', [OperationsPaymentsController::class, 'index'])->name('payments.index');
Route::get('/payments/export', [OperationsPaymentsController::class, 'export'])->name('payments.export');
Route::get('/api/payments', [OperationsPaymentsController::class, 'listing'])->name('api.payments.listing');
Route::get('/api/payments/quests/{quest}', [OperationsPaymentsController::class, 'detail'])->name('api.payments.detail');
Route::post('/api/payments/quests/{quest}/requests', [OperationsPaymentsController::class, 'requestAction'])->middleware('throttle:30,1')->name('api.payments.requests');

Route::get('/verifications', [OperationsVerificationsController::class, 'index'])->name('verifications.index');
Route::get('/api/verifications', [OperationsVerificationsController::class, 'listing'])->name('api.verifications.listing');
Route::get('/api/verifications/{verification}', [OperationsVerificationsController::class, 'detail'])->name('api.verifications.detail');
Route::post('/api/verifications/{verification}/decide', [OperationsVerificationsController::class, 'decide'])->middleware('throttle:60,1')->name('api.verifications.decide');
Route::post('/api/verifications/{verification}/escalate', [OperationsVerificationsController::class, 'escalate'])->middleware('throttle:30,1')->name('api.verifications.escalate');
Route::get('/api/verifications/{verification}/document', [OperationsVerificationsController::class, 'document'])->middleware('throttle:120,1')->name('api.verifications.document');

Route::get('/reviews', [OperationsReviewsController::class, 'index'])->name('reviews.index');
Route::get('/api/reviews', [OperationsReviewsController::class, 'listing'])->name('api.reviews.listing');
Route::get('/api/reviews/manipulation/{freelancer}/breakdown', [OperationsReviewsController::class, 'manipulationBreakdown'])->name('api.reviews.manipulation.breakdown');
Route::get('/api/reviews/manipulation/export/{reportType}', [OperationsReviewsController::class, 'exportManipulation'])->name('api.reviews.manipulation.export');
Route::get('/api/reviews/clusters/{cluster}', [OperationsReviewsController::class, 'clusterDetail'])->name('api.reviews.clusters.detail');
Route::get('/api/reviews/{review}', [OperationsReviewsController::class, 'detail'])->name('api.reviews.detail');
Route::post('/api/reviews/{review}/approve', [OperationsReviewsController::class, 'approve'])->middleware('throttle:60,1')->name('api.reviews.approve');
Route::post('/api/reviews/{review}/remove', [OperationsReviewsController::class, 'remove'])->middleware('throttle:30,1')->name('api.reviews.remove');
Route::post('/api/reviews/{review}/revision', [OperationsReviewsController::class, 'requestRevision'])->middleware('throttle:60,1')->name('api.reviews.revision');
Route::post('/api/reviews/{review}/amendment', [OperationsReviewsController::class, 'requestAmendment'])->middleware('throttle:60,1')->name('api.reviews.amendment');
Route::post('/api/reviews/{review}/flag', [OperationsReviewsController::class, 'flag'])->middleware('throttle:60,1')->name('api.reviews.flag');
Route::post('/api/reviews/appeals/{appeal}/resolve', [OperationsReviewsController::class, 'resolveAppeal'])->middleware('throttle:30,1')->name('api.reviews.appeals.resolve');

Route::get('/tasks', [OperationsTasksController::class, 'index'])->name('tasks.index');
Route::get('/api/tasks', [OperationsTasksController::class, 'listing'])->name('api.tasks.listing');
Route::get('/api/tasks/{task}', [OperationsTasksController::class, 'detail'])->name('api.tasks.detail');
Route::patch('/tasks/{task}/status', [OperationsTasksController::class, 'status'])->middleware('throttle:60,1')->name('tasks.status');

Route::get('/portfolios', [OperationsPortfoliosController::class, 'index'])->name('portfolios.index');
Route::patch('/portfolios/{portfolio}/visibility', [OperationsPortfoliosController::class, 'updateVisibility'])
    ->middleware('throttle:60,1')
    ->name('portfolios.visibility.update');

Route::get('/portfolio-review', [\App\Http\Controllers\Admin\AdminPortfolioReviewController::class, 'operationsIndex'])->name('portfolio-review.index');
Route::get('/portfolio-review/{portfolio}', [\App\Http\Controllers\Admin\AdminPortfolioReviewController::class, 'operationsShow'])->name('portfolio-review.show');
Route::patch('/portfolio-review/{portfolio}', [\App\Http\Controllers\Admin\AdminPortfolioReviewController::class, 'operationsUpdate'])
    ->middleware('throttle:40,1')
    ->name('portfolio-review.update');

Route::get('/review-integrity', [OperationsReviewIntegrityController::class, 'index'])->name('review-integrity.index');
Route::get('/api/review-integrity', [OperationsReviewIntegrityController::class, 'listing'])->name('api.review-integrity.listing');
Route::get('/api/review-integrity/cases/{case}', [OperationsReviewIntegrityController::class, 'detail'])->name('api.review-integrity.detail');
Route::post('/api/review-integrity/cases', [OperationsReviewIntegrityController::class, 'openCase'])->middleware('throttle:30,1')->name('api.review-integrity.open');
Route::patch('/api/review-integrity/cases/{case}/findings', [OperationsReviewIntegrityController::class, 'saveFindings'])->middleware('throttle:30,1')->name('api.review-integrity.findings');
Route::post('/api/review-integrity/cases/{case}/flag', [OperationsReviewIntegrityController::class, 'bulkFlag'])->middleware('throttle:30,1')->name('api.review-integrity.flag');
Route::post('/api/review-integrity/cases/{case}/escalate', [OperationsReviewIntegrityController::class, 'escalate'])->middleware('throttle:30,1')->name('api.review-integrity.escalate');

Route::get('/escrow-anomalies', [OperationsEscrowAnomaliesController::class, 'index'])->name('escrow-anomalies.index');
Route::get('/api/escrow-anomalies', [OperationsEscrowAnomaliesController::class, 'listing'])->name('api.escrow-anomalies.listing');
Route::get('/api/escrow-anomalies/quests/{quest}', [OperationsEscrowAnomaliesController::class, 'detail'])->name('api.escrow-anomalies.detail');
Route::post('/api/escrow-anomalies/quests/{quest}/outreach', [OperationsEscrowAnomaliesController::class, 'outreach'])->middleware('throttle:30,1')->name('api.escrow-anomalies.outreach');
Route::patch('/api/escrow-anomalies/notes/{note}/resolve', [OperationsEscrowAnomaliesController::class, 'resolveNote'])->middleware('throttle:30,1')->name('api.escrow-anomalies.notes.resolve');

Route::get('/sanction-appeals', [OperationsSanctionAppealsController::class, 'index'])->name('sanction-appeals.index');
Route::get('/api/sanction-appeals', [OperationsSanctionAppealsController::class, 'listing'])->name('api.sanction-appeals.listing');
Route::get('/api/sanction-appeals/{appeal}', [OperationsSanctionAppealsController::class, 'detail'])->name('api.sanction-appeals.detail');
Route::post('/api/sanction-appeals/{appeal}/approve', [OperationsSanctionAppealsController::class, 'approve'])->middleware('throttle:30,1')->name('api.sanction-appeals.approve');
Route::post('/api/sanction-appeals/{appeal}/reject', [OperationsSanctionAppealsController::class, 'reject'])->middleware('throttle:30,1')->name('api.sanction-appeals.reject');
Route::post('/api/sanction-appeals/{appeal}/escalate', [OperationsSanctionAppealsController::class, 'escalate'])->middleware('throttle:30,1')->name('api.sanction-appeals.escalate');

Route::get('/communications-log', [OperationsCommunicationsViewerController::class, 'index'])->name('communications-log.index');
Route::get('/api/communications-log', [OperationsCommunicationsViewerController::class, 'listing'])->name('api.communications-log.listing');

Route::get('/knowledge-base', [OperationsKnowledgeBaseController::class, 'index'])->name('knowledge-base.index');
Route::get('/api/knowledge-base', [OperationsKnowledgeBaseController::class, 'listing'])->name('api.knowledge-base.listing');
Route::get('/api/knowledge-base/articles/{article}', [OperationsKnowledgeBaseController::class, 'article'])->name('api.knowledge-base.article');
Route::post('/api/knowledge-base/suggest', [OperationsKnowledgeBaseController::class, 'suggest'])->middleware('throttle:20,1')->name('api.knowledge-base.suggest');

Route::get('/category-health', [OperationsCategoryHealthController::class, 'index'])->name('category-health.index');
Route::get('/api/category-health', [OperationsCategoryHealthController::class, 'dashboard'])->name('api.category-health.dashboard');

Route::get('/badge-requests', [OperationsBadgeRequestsController::class, 'index'])->name('badge-requests.index');
Route::get('/api/badge-requests', [OperationsBadgeRequestsController::class, 'listing'])->name('api.badge-requests.listing');
Route::get('/api/badge-requests/{badgeRequest}', [OperationsBadgeRequestsController::class, 'detail'])->name('api.badge-requests.detail');
Route::post('/api/badge-requests/{badgeRequest}/approve', [OperationsBadgeRequestsController::class, 'approve'])->middleware('throttle:30,1')->name('api.badge-requests.approve');
Route::post('/api/badge-requests/{badgeRequest}/reject', [OperationsBadgeRequestsController::class, 'reject'])->middleware('throttle:30,1')->name('api.badge-requests.reject');
Route::post('/api/badge-requests/{badgeRequest}/escalate', [OperationsBadgeRequestsController::class, 'escalate'])->middleware('throttle:30,1')->name('api.badge-requests.escalate');

Route::get('/customer-support', [AdminCustomerSupportController::class, 'operationsIndex'])->name('customer-support.index');
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
Route::get('/api/customer-support/users/{user}/context', [AdminCustomerSupportController::class, 'userContext'])->name('api.customer-support.user-context');
Route::get('/api/customer-support/gifs', [AdminCustomerSupportController::class, 'gifSearch'])->name('api.customer-support.gifs');

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

Route::get('/team-chat', [OperationsTeamChatController::class, 'index'])->name('team-chat.index');
Route::get('/api/team-chat/bootstrap', [OperationsTeamChatController::class, 'bootstrap'])->name('api.team-chat.bootstrap');
Route::get('/api/team-chat/rooms/{room}/messages', [OperationsTeamChatController::class, 'messages'])->name('api.team-chat.messages');
Route::post('/api/team-chat/rooms/{room}/messages', [OperationsTeamChatController::class, 'send'])->middleware('throttle:120,1')->name('api.team-chat.send');
Route::post('/api/team-chat/messages/{message}/react', [OperationsTeamChatController::class, 'react'])->middleware('throttle:120,1')->name('api.team-chat.react');
Route::post('/api/team-chat/rooms/{room}/messages/{message}/pin', [OperationsTeamChatController::class, 'pin'])->middleware('throttle:30,1')->name('api.team-chat.pin');
Route::post('/api/team-chat/rooms/{room}/read', [OperationsTeamChatController::class, 'read'])->middleware('throttle:120,1')->name('api.team-chat.read');
Route::post('/api/team-chat/rooms/{room}/typing', [OperationsTeamChatController::class, 'typing'])->middleware('throttle:180,1')->name('api.team-chat.typing');
Route::get('/api/team-chat/rooms/{room}/search', [OperationsTeamChatController::class, 'search'])->name('api.team-chat.search');
Route::get('/api/team-chat/presence', [OperationsTeamChatController::class, 'presence'])->name('api.team-chat.presence');
Route::delete('/api/team-chat/messages/{message}', [OperationsTeamChatController::class, 'remove'])->middleware('throttle:30,1')->name('api.team-chat.remove');
Route::get('/api/team-chat/gifs', [\App\Http\Controllers\Shared\ChatGifController::class, 'index'])->name('api.team-chat.gifs');
