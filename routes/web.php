<?php

use App\Enums\CredentialType;
use App\Http\Controllers\AccountDeactivateController;
use App\Http\Controllers\AccountDeleteUserController;
use App\Http\Controllers\AccountHubController;
use App\Http\Controllers\AccountPresenceController;
use App\Http\Controllers\AccountQuestCategoriesController;
use App\Http\Controllers\AccountSecurityController;
use App\Http\Controllers\AccountUpdateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardListController;
use App\Http\Controllers\DashboardTrustGuideController;
use App\Http\Controllers\FreelancerCredentialController;
use App\Http\Controllers\FreelancerCredentialVisibilityController;
use App\Http\Controllers\FreelancerPortfolioController;
use App\Http\Controllers\FreelancerPortfoliosDirectoryController;
use App\Http\Controllers\FreelancerReviewsDirectoryController;
use App\Http\Controllers\Legal\LegalPageController;
use App\Http\Controllers\NotificationReadController;
use App\Http\Controllers\Public\LandingController;
use App\Http\Controllers\Public\HelpContentController;
use App\Http\Controllers\Public\NewsletterController;
use App\Http\Controllers\Public\PublicFreelancerProfileController;
use App\Http\Controllers\QuestClientProposalsController;
use App\Http\Controllers\QuestBookmarkController;
use App\Http\Controllers\QuestContentReportController;
use App\Http\Controllers\ProposalClarificationController;
use App\Http\Controllers\QuestBudgetGuidanceController;
use App\Http\Controllers\QuestController;
use App\Http\Controllers\Support\CustomerSupportChatController;
use App\Http\Controllers\QuestConversationController;
use App\Http\Controllers\QuestDisputeController;
use App\Http\Controllers\QuestDisputeMessageController;
use App\Http\Controllers\QuestDisputeMutualResolveController;
use App\Http\Controllers\QuestDisputeSettlementController;
use App\Http\Controllers\QuestExploreController;
use App\Http\Controllers\QuestFieldProfileController;
use App\Http\Controllers\QuestFileController;
use App\Http\Controllers\QuestInviteController;
use App\Http\Controllers\QuestOfferController;
use App\Http\Controllers\QuestProposalController;
use App\Http\Controllers\QuestProposalLifecycleController;
use App\Http\Controllers\QuestProposalPdfController;
use App\Http\Controllers\QuestCompletionController;
use App\Http\Controllers\QuestProposalFundingIntentController;
use App\Http\Controllers\QuestWizardController;
use App\Http\Controllers\Payments\PaystackCallbackController;
use App\Http\Controllers\Payments\PaystackWebhookController;
use App\Http\Controllers\Wallet\WalletController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\UserFollowController;
use App\Http\Controllers\UserFreelancerSearchController;
use App\Http\Controllers\UserVerificationController;
use App\Http\Controllers\Auth\OperationsStaffInvitationController;
use App\Http\Controllers\Admin\AdminUsersController;
use Illuminate\Support\Facades\Route;

Route::get('/terms-of-service', [LegalPageController::class, 'terms'])->name('legal.terms');
Route::get('/privacy-policy', [LegalPageController::class, 'privacy'])->name('legal.privacy');

Route::get('/', LandingController::class)->name('home');
Route::get('/help', HelpContentController::class)->name('help.index');

Route::middleware('signed')->group(function (): void {
    Route::get('/support/rate/{ticket:uuid}', [CustomerSupportChatController::class, 'rateShow'])->name('support.rate.show');
    Route::post('/support/rate/{ticket:uuid}', [CustomerSupportChatController::class, 'rateSubmit'])->name('support.rate.submit');
});

Route::post('/newsletter', [NewsletterController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('newsletter.subscribe');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/freelancers/{slug}/reviews', FreelancerReviewsDirectoryController::class)->name('freelancers.public.reviews');
Route::get('/freelancers/{slug}/portfolios', FreelancerPortfoliosDirectoryController::class)->name('freelancers.public.portfolios');
Route::get('/freelancers/{slug}', PublicFreelancerProfileController::class)->name('freelancers.public');

Route::get('/portfolio', [FreelancerPortfolioController::class, 'index'])->name('portfolio.index');

Route::middleware(['signed', 'throttle:12,1'])->group(function (): void {
    Route::get('/operations-invitation/{user}', [OperationsStaffInvitationController::class, 'show'])
        ->name('operations.invitation.show');
    Route::post('/operations-invitation/{user}', [OperationsStaffInvitationController::class, 'update'])
        ->name('operations.invitation.update');
});

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::post('/webhooks/paystack', PaystackWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.paystack');
Route::get('/payments/paystack/callback', PaystackCallbackController::class)
    ->middleware(['auth', 'verified'])
    ->name('payments.paystack.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/impersonation/stop', [AdminUsersController::class, 'stopImpersonating'])
        ->middleware('throttle:20,1')
        ->name('impersonation.stop');

    // GET avoids duplicate POST handling and reduces concurrent DB/session pressure (helps on Windows dev stacks).
    Route::get('/notifications/{id}/next', NotificationReadController::class)
        ->whereUuid('id')
        ->middleware('throttle:120,1')
        ->name('notifications.read');

    Route::get('/account', [AccountHubController::class, 'show'])->name('account.show');
    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/bank-accounts', [WalletController::class, 'storeBankAccount'])->middleware('throttle:20,1')->name('wallet.bank-accounts.store');
    Route::post('/wallet/withdraw', [WalletController::class, 'withdraw'])->middleware('throttle:10,1')->name('wallet.withdraw');
    Route::post('/wallet/resolve-account', [WalletController::class, 'resolveAccount'])->middleware('throttle:30,1')->name('wallet.resolve-account');
    Route::patch('/account/details', [AccountUpdateController::class, 'details'])->name('account.details');
    Route::patch('/account/power-hours', [AccountUpdateController::class, 'powerHours'])
        ->middleware('freelancer')
        ->name('account.power-hours');
    Route::patch('/account/visibility', [AccountUpdateController::class, 'visibility'])->name('account.visibility');
    Route::patch('/account/credentials/{freelancerCredential}', FreelancerCredentialVisibilityController::class)
        ->whereNumber('freelancerCredential')
        ->name('account.credentials.visibility');
    Route::post('/account/deactivate', AccountDeactivateController::class)
        ->middleware('throttle:6,1')
        ->name('account.deactivate');
    Route::post('/users/{slug}/follow', [UserFollowController::class, 'toggle'])
        ->middleware('throttle:60,1')
        ->name('users.follow.toggle');

    Route::patch('/account/presence', AccountPresenceController::class)->name('account.presence');

    Route::get('/api/support/widget/bootstrap', [CustomerSupportChatController::class, 'bootstrap'])->name('api.support.widget.bootstrap');
    Route::post('/api/support/chat/start', [CustomerSupportChatController::class, 'startJson'])->middleware('throttle:10,1')->name('api.support.chat.start');
    Route::get('/api/support/chat/{ticket}/open', [CustomerSupportChatController::class, 'openJson'])->name('api.support.chat.open');
    Route::post('/api/support/chat/{ticket}/rate', [CustomerSupportChatController::class, 'rateJson'])->middleware('throttle:20,1')->name('api.support.chat.rate');
    Route::post('/api/support/chat/{ticket}/feedback', [CustomerSupportChatController::class, 'feedbackJson'])->middleware('throttle:20,1')->name('api.support.chat.feedback');
    Route::get('/support/chat', [CustomerSupportChatController::class, 'index'])->name('support.chat.index');
    Route::post('/support/chat', [CustomerSupportChatController::class, 'start'])->middleware('throttle:10,1')->name('support.chat.start');
    Route::get('/support/chat/{ticket}', [CustomerSupportChatController::class, 'show'])->name('support.chat.show');
    Route::get('/api/support/chat/{ticket}/messages', [CustomerSupportChatController::class, 'messages'])->name('api.support.chat.messages');
    Route::post('/api/support/chat/{ticket}/messages', [CustomerSupportChatController::class, 'send'])->middleware('throttle:120,1')->name('api.support.chat.send');
    Route::post('/api/support/chat/{ticket}/typing', [CustomerSupportChatController::class, 'typing'])->middleware('throttle:180,1')->name('api.support.chat.typing');
    Route::get('/api/support/chat/{ticket}/typing-state', [CustomerSupportChatController::class, 'typingState'])->name('api.support.chat.typing-state');
    Route::post('/api/support/chat/{ticket}/read', [CustomerSupportChatController::class, 'read'])->middleware('throttle:120,1')->name('api.support.chat.read');
    Route::post('/api/support/chat/{ticket}/messages/{message}/react', [CustomerSupportChatController::class, 'react'])->middleware('throttle:120,1')->name('api.support.chat.react');
    Route::get('/api/support/gifs', [CustomerSupportChatController::class, 'gifSearch'])->name('api.support.gifs');

    Route::get('/account/security', [AccountSecurityController::class, 'edit'])->name('account.security.edit');
    Route::post('/account/security/avatar', [AccountSecurityController::class, 'updateAvatar'])
        ->middleware('throttle:20,1')
        ->name('account.security.avatar');
    Route::delete('/account', AccountDeleteUserController::class)->name('account.destroy');
    Route::redirect('/profile', '/account/security');

    Route::get('/dashboard/lists/{list}', [DashboardListController::class, 'show'])->name('dashboard.lists.show');
    Route::get('/dashboard/guides/trust', DashboardTrustGuideController::class)->name('dashboard.trust-guide');

    Route::get('/disputes', [QuestDisputeController::class, 'index'])->name('disputes.index');
    Route::get('/disputes/{dispute}', [QuestDisputeController::class, 'show'])->name('disputes.show');
    Route::post('/disputes/{dispute}/messages', [QuestDisputeMessageController::class, 'store'])
        ->middleware('throttle:45,1')
        ->name('disputes.messages.store');
    Route::post('/disputes/{dispute}/settlement-offers', [QuestDisputeSettlementController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('disputes.settlement-offers.store');
    Route::post('/disputes/{dispute}/settlement-offers/{settlement_offer}/accept', [QuestDisputeSettlementController::class, 'accept'])
        ->middleware('throttle:20,1')
        ->whereNumber('settlement_offer')
        ->name('disputes.settlement-offers.accept');
    Route::post('/disputes/{dispute}/settlement-offers/{settlement_offer}/decline', [QuestDisputeSettlementController::class, 'decline'])
        ->middleware('throttle:20,1')
        ->whereNumber('settlement_offer')
        ->name('disputes.settlement-offers.decline');
    Route::post('/disputes/{dispute}/mutual-resolve', [QuestDisputeMutualResolveController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('disputes.mutual-resolve.store');

    Route::get('/quests/explore', QuestExploreController::class)->name('quests.explore');
    Route::get('/quests', [QuestController::class, 'index'])->name('quests.index');
    Route::get('/quests/create', [QuestController::class, 'create'])->name('quests.create');
    Route::post('/quests', [QuestController::class, 'store'])->name('quests.store');
    Route::get('/taggable-freelancers', UserFreelancerSearchController::class)->name('users.freelancers.search');
    Route::get('/quests/field-profile', QuestFieldProfileController::class)->name('quests.field-profile');
    Route::get('/quests/budget-guidance', QuestBudgetGuidanceController::class)->name('quests.budget-guidance');
    Route::post('/quests/wizard/validate-step', [QuestWizardController::class, 'validateStep'])
        ->middleware('throttle:60,1')
        ->name('quests.wizard.validate-step');
    Route::post('/quests/{quest}/proposals', [QuestOfferController::class, 'store'])
        ->middleware(['freelancer', 'throttle:15,1'])
        ->name('quests.proposals.store');
    Route::post('/quests/{quest}/offers', [QuestOfferController::class, 'store'])
        ->middleware('freelancer');
    Route::post('/quests/{quest}/files', [QuestFileController::class, 'store'])->name('quests.files.store');
    Route::delete('/quests/{quest}/files/{file}', [QuestFileController::class, 'destroy'])
        ->whereNumber('file')
        ->name('quests.files.destroy');
    Route::post('/quests/{quest}/invites', [QuestInviteController::class, 'store'])->name('quests.invites.store');
    Route::delete('/quests/{quest}/invites/{freelancer}', [QuestInviteController::class, 'destroy'])
        ->whereNumber('freelancer')
        ->name('quests.invites.destroy');
    Route::get('/quests/{quest}/freelancers/search', [QuestController::class, 'searchFreelancers'])->name('quests.freelancers.search');
    Route::post('/quests/{quest}/bookmark', [QuestBookmarkController::class, 'store'])->name('quests.bookmark.store');
    Route::delete('/quests/{quest}/bookmark', [QuestBookmarkController::class, 'destroy'])->name('quests.bookmark.destroy');

    Route::get('/quests/{quest}/proposals', [QuestClientProposalsController::class, 'index'])
        ->name('quests.client.proposals.index');

    Route::get('/quests/{quest}/proposals/create', [QuestProposalController::class, 'create'])
        ->middleware('freelancer')
        ->name('quests.proposals.create');
    Route::get('/quests/{quest}/proposals/{offer}/edit', [QuestProposalController::class, 'edit'])
        ->middleware('freelancer')
        ->whereNumber('offer')
        ->name('quests.proposals.edit');
    Route::patch('/quests/{quest}/proposals/{offer}', [QuestOfferController::class, 'update'])
        ->middleware(['freelancer', 'throttle:15,1'])
        ->whereNumber('offer')
        ->name('quests.proposals.update');
    Route::post('/quests/{quest}/proposals/{offer}/shortlist', [QuestProposalLifecycleController::class, 'shortlist'])
        ->middleware('throttle:30,1')
        ->whereNumber('offer')
        ->name('quests.proposals.shortlist');
    Route::post('/quests/{quest}/proposals/{offer}/unshortlist', [QuestProposalLifecycleController::class, 'unshortlist'])
        ->middleware('throttle:30,1')
        ->whereNumber('offer')
        ->name('quests.proposals.unshortlist');
    Route::post('/quests/{quest}/proposals/{offer}/pin', [QuestProposalLifecycleController::class, 'pin'])
        ->middleware('throttle:30,1')
        ->whereNumber('offer')
        ->name('quests.proposals.pin');
    Route::post('/quests/{quest}/proposals/{offer}/decline', [QuestProposalLifecycleController::class, 'decline'])
        ->middleware('throttle:20,1')
        ->whereNumber('offer')
        ->name('quests.proposals.decline');
    Route::post('/quests/{quest}/proposals/{offer}/accept', [QuestProposalLifecycleController::class, 'accept'])
        ->middleware('throttle:10,1')
        ->whereNumber('offer')
        ->name('quests.proposals.accept');
    Route::post('/quests/{quest}/proposals/{offer}/confirm-award', [QuestProposalLifecycleController::class, 'confirmAward'])
        ->middleware(['freelancer', 'throttle:10,1'])
        ->whereNumber('offer')
        ->name('quests.proposals.confirm-award');
    Route::get('/quests/{quest}/proposals/{offer}/clarify', [ProposalClarificationController::class, 'show'])
        ->whereNumber('offer')
        ->name('quests.proposals.clarify');
    Route::post('/quests/{quest}/proposals/{offer}/clarify/ask', [ProposalClarificationController::class, 'ask'])
        ->middleware('throttle:30,1')
        ->whereNumber('offer')
        ->name('quests.proposals.clarify.ask');
    Route::post('/quests/{quest}/proposals/{offer}/clarify/answer', [ProposalClarificationController::class, 'answer'])
        ->middleware(['freelancer', 'throttle:30,1'])
        ->whereNumber('offer')
        ->name('quests.proposals.clarify.answer');
    Route::post('/quests/{quest}/proposals/{offer}/escrow-funded', [QuestProposalLifecycleController::class, 'markEscrowFunded'])
        ->middleware('throttle:10,1')
        ->whereNumber('offer')
        ->name('quests.proposals.escrow-funded');
    Route::post('/quests/{quest}/proposals/{offer}/funding-intent', [QuestProposalFundingIntentController::class, 'store'])
        ->middleware('throttle:20,1')
        ->whereNumber('offer')
        ->name('quests.proposals.funding-intent.store');
    Route::post('/quests/{quest}/acknowledge-delivery', [QuestCompletionController::class, 'acknowledgeDelivery'])
        ->middleware('throttle:10,1')
        ->name('quests.acknowledge-delivery');
    Route::post('/quests/{quest}/release-funds', [QuestCompletionController::class, 'releaseFunds'])
        ->middleware('throttle:10,1')
        ->name('quests.release-funds');
    Route::post('/quests/{quest}/complete', [QuestCompletionController::class, 'markComplete'])
        ->middleware('throttle:10,1')
        ->name('quests.complete');
    Route::post('/quests/{quest}/proposals/{offer}/withdraw', [QuestProposalLifecycleController::class, 'withdraw'])
        ->middleware(['freelancer', 'throttle:10,1'])
        ->whereNumber('offer')
        ->name('quests.proposals.withdraw');
    Route::get('/quests/{quest}/proposals/{offer}/pdf', QuestProposalPdfController::class)
        ->whereNumber('offer')
        ->name('quests.proposals.pdf');
    Route::get('/quests/{quest}/proposals/{offer}', [QuestProposalController::class, 'show'])
        ->whereNumber('offer')
        ->name('quests.proposals.show');
    Route::get('/quests/{quest}/disputes/create', [QuestDisputeController::class, 'create'])->name('quests.disputes.create');
    Route::post('/quests/{quest}/disputes', [QuestDisputeController::class, 'store'])
        ->middleware('throttle:12,1')
        ->name('quests.disputes.store');
    Route::get('/quests/{quest}/messages/{contact?}', [QuestConversationController::class, 'show'])
        ->name('quests.messages.show');
    Route::post('/quests/{quest}/messages/{contact?}', [QuestConversationController::class, 'store'])
        ->middleware('throttle:35,1')
        ->name('quests.messages.store');
    Route::post('/quests/{quest}/messages/{contact?}/read', [QuestConversationController::class, 'read'])
        ->middleware('throttle:120,1')
        ->name('quests.messages.read');
    Route::post('/quests/{quest}/messages/{contact?}/typing', [QuestConversationController::class, 'typing'])
        ->middleware('throttle:180,1')
        ->name('quests.messages.typing');
    Route::post('/quests/{quest}/reports', [QuestContentReportController::class, 'storeQuest'])
        ->middleware('throttle:20,1')
        ->name('quests.reports.store');
    Route::post('/quests/{quest}/proposals/{offer}/reports', [QuestContentReportController::class, 'storeProposal'])
        ->middleware('throttle:20,1')
        ->whereNumber('offer')
        ->name('quests.proposals.reports.store');

    Route::patch('/quests/{quest}', [QuestController::class, 'update'])->name('quests.update');
    Route::delete('/quests/{quest}', [QuestController::class, 'destroy'])->name('quests.destroy');
    Route::get('/quests/{quest}', [QuestController::class, 'show'])->name('quests.show');

    Route::post('/reviews', [ReviewController::class, 'store'])
        ->middleware('throttle:15,1')
        ->name('reviews.store');
    Route::patch('/reviews/{review}', [ReviewController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('reviews.update');

    Route::get('/verifications', [UserVerificationController::class, 'index'])->name('verifications.index');
    Route::post('/verifications', [UserVerificationController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('verifications.store');

    Route::post('/portfolio/{portfolio}/favorite', [FreelancerPortfolioController::class, 'favorite'])
        ->name('portfolio.favorite');

    Route::patch('/account/quest-categories', [AccountQuestCategoriesController::class, 'update'])
        ->middleware('freelancer')
        ->name('account.quest-categories.update');

    Route::middleware('freelancer')->group(function () {
        $credentialTypes = array_map(fn (CredentialType $t) => $t->value, CredentialType::cases());

        Route::get('/account/credentials/create', function () {
            return redirect()->route('account.credentials.create', ['type' => CredentialType::Insurance->value]);
        });

        Route::get('/account/credentials', [FreelancerCredentialController::class, 'index'])->name('account.credentials.index');
        Route::get('/account/credentials/{type}/create', [FreelancerCredentialController::class, 'create'])
            ->whereIn('type', $credentialTypes)
            ->name('account.credentials.create');
        Route::post('/account/credentials/{type}', [FreelancerCredentialController::class, 'store'])
            ->whereIn('type', $credentialTypes)
            ->name('account.credentials.store');
        Route::get('/account/credentials/{freelancerCredential}/edit', [FreelancerCredentialController::class, 'edit'])
            ->whereNumber('freelancerCredential')
            ->name('account.credentials.edit');
        Route::put('/account/credentials/{freelancerCredential}', [FreelancerCredentialController::class, 'update'])
            ->whereNumber('freelancerCredential')
            ->name('account.credentials.update');
        Route::delete('/account/credentials/{freelancerCredential}', [FreelancerCredentialController::class, 'destroy'])
            ->whereNumber('freelancerCredential')
            ->name('account.credentials.destroy');

        Route::get('/portfolio/manage', [FreelancerPortfolioController::class, 'manage'])->name('portfolio.manage');
        Route::get('/portfolio/create', [FreelancerPortfolioController::class, 'create'])->name('portfolio.create');
        Route::post('/portfolio', [FreelancerPortfolioController::class, 'store'])->name('portfolio.store');
        Route::get('/portfolio/{portfolio}/edit', [FreelancerPortfolioController::class, 'edit'])->name('portfolio.edit');
        Route::put('/portfolio/{portfolio}', [FreelancerPortfolioController::class, 'update'])->name('portfolio.update');
        Route::delete('/portfolio/{portfolio}', [FreelancerPortfolioController::class, 'destroy'])->name('portfolio.destroy');
    });

    Route::middleware(['redirect_operations_staff_from_admin', 'super_admin', 'throttle:480,1'])
        ->prefix('admin')
        ->name('admin.')
        ->group(base_path('routes/admin.php'));

    Route::middleware(['operations_staff', 'staff_role_group_access', 'throttle:480,1'])
        ->prefix('operations')
        ->name('operations.')
        ->group(base_path('routes/operations.php'));
});

Route::redirect('/hq', '/admin', 301);
Route::redirect('/hq/', '/admin/', 301);
Route::get('/hq/{path}', function (string $path) {
    return redirect('/admin/'.$path, 301);
})->where('path', '.*');

Route::get('/portfolio/{portfolio}', [FreelancerPortfolioController::class, 'show'])->name('portfolio.show');

if (app()->environment('local')) {
    Route::get('/preview/errors/{page}', function (string $page) {
        return match ($page) {
            '403' => \Inertia\Inertia::render('Errors/Forbidden'),
            '404' => \Inertia\Inertia::render('Errors/NotFound'),
            'maintenance' => \Inertia\Inertia::render('Errors/Maintenance', [
                'message' => 'Our crew is upgrading escrow rails, polishing quests, and tightening safety belts.',
                'returnTime' => now()->addHours(2)->addMinutes(30)->format('Y-m-d\TH:i'),
            ]),
            default => abort(404),
        };
    })->whereIn('page', ['403', '404', 'maintenance']);
}

require __DIR__.'/auth.php';
