<?php

use App\Http\Controllers\Operations\OperationsCommunicationsController;
use App\Http\Controllers\Operations\OperationsDashboardController;
use App\Http\Controllers\Operations\OperationsDisputesController;
use App\Http\Controllers\Operations\OperationsEscalationController;
use App\Http\Controllers\Operations\OperationsModerationController;
use App\Http\Controllers\Operations\OperationsPaymentsController;
use App\Http\Controllers\Operations\OperationsPortfoliosController;
use App\Http\Controllers\Operations\OperationsProposalsController;
use App\Http\Controllers\Operations\OperationsQuestsController;
use App\Http\Controllers\Operations\OperationsReviewsController;
use App\Http\Controllers\Operations\OperationsSupportHubController;
use App\Http\Controllers\Operations\OperationsTasksController;
use App\Http\Controllers\Operations\OperationsUsersController;
use App\Http\Controllers\Operations\OperationsVerificationsController;
use Illuminate\Support\Facades\Route;

Route::get('/', OperationsDashboardController::class)->name('dashboard');
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

Route::redirect('/communications', '/operations/support')->name('communications.index');
Route::post('/communications/bulk-messages', [OperationsCommunicationsController::class, 'storeBulkMessage'])->middleware('throttle:10,1')->name('communications.bulk-messages.store');
Route::post('/communications/tickets', [OperationsCommunicationsController::class, 'storeTicket'])->middleware('throttle:30,1')->name('communications.tickets.store');
Route::patch('/communications/tickets/{ticket}/status', [OperationsCommunicationsController::class, 'updateTicketStatus'])->middleware('throttle:60,1')->name('communications.tickets.status');

Route::get('/disputes', [OperationsDisputesController::class, 'index'])->name('disputes.index');
Route::get('/disputes/export', [OperationsDisputesController::class, 'export'])->name('disputes.export');

Route::get('/payments', [OperationsPaymentsController::class, 'index'])->name('payments.index');
Route::get('/payments/export', [OperationsPaymentsController::class, 'export'])->name('payments.export');
Route::get('/verifications', [OperationsVerificationsController::class, 'index'])->name('verifications.index');
Route::get('/reviews', [OperationsReviewsController::class, 'index'])->name('reviews.index');
Route::get('/tasks', [OperationsTasksController::class, 'index'])->name('tasks.index');
Route::patch('/tasks/{task}/status', [OperationsTasksController::class, 'status'])->middleware('throttle:60,1')->name('tasks.status');

Route::get('/portfolios', [OperationsPortfoliosController::class, 'index'])->name('portfolios.index');
Route::patch('/portfolios/{portfolio}/visibility', [OperationsPortfoliosController::class, 'updateVisibility'])
    ->middleware('throttle:60,1')
    ->name('portfolios.visibility.update');
