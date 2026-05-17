<?php

use App\Http\Controllers\Operations\OperationsDashboardController;
use App\Http\Controllers\Operations\OperationsDisputesController;
use App\Http\Controllers\Operations\OperationsPaymentsController;
use App\Http\Controllers\Operations\OperationsPortfoliosController;
use App\Http\Controllers\Operations\OperationsQuestsController;
use App\Http\Controllers\Operations\OperationsUsersController;
use Illuminate\Support\Facades\Route;

Route::get('/', OperationsDashboardController::class)->name('dashboard');
Route::get('/exports/dashboard-metrics.csv', [OperationsDashboardController::class, 'export'])->name('dashboard.export');

Route::get('/quests', [OperationsQuestsController::class, 'index'])->name('quests.index');
Route::get('/quests/export', [OperationsQuestsController::class, 'export'])->name('quests.export');

Route::get('/users', [OperationsUsersController::class, 'index'])->name('users.index');
Route::get('/users/export', [OperationsUsersController::class, 'export'])->name('users.export');
Route::patch('/users/{user}/suspension', [OperationsUsersController::class, 'updateSuspension'])
    ->middleware('throttle:60,1')
    ->name('users.suspension.update');

Route::get('/disputes', [OperationsDisputesController::class, 'index'])->name('disputes.index');
Route::get('/disputes/export', [OperationsDisputesController::class, 'export'])->name('disputes.export');

Route::get('/payments', [OperationsPaymentsController::class, 'index'])->name('payments.index');
Route::get('/payments/export', [OperationsPaymentsController::class, 'export'])->name('payments.export');

Route::get('/portfolios', [OperationsPortfoliosController::class, 'index'])->name('portfolios.index');
Route::patch('/portfolios/{portfolio}/visibility', [OperationsPortfoliosController::class, 'updateVisibility'])
    ->middleware('throttle:60,1')
    ->name('portfolios.visibility.update');
