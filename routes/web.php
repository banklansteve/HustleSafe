<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardListController;
use App\Http\Controllers\DashboardTrustGuideController;
use App\Http\Controllers\QuestCreatePlaceholderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\LandingController;
use App\Http\Controllers\Public\NewsletterController;
use App\Http\Controllers\Public\PublicFreelancerProfileController;
use App\Http\Controllers\QuestExploreController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\UserVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');

Route::post('/newsletter', [NewsletterController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('newsletter.subscribe');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/freelancers/{slug}', PublicFreelancerProfileController::class)->name('freelancers.public');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard/lists/{list}', [DashboardListController::class, 'show'])->name('dashboard.lists.show');
    Route::get('/dashboard/guides/trust', DashboardTrustGuideController::class)->name('dashboard.trust-guide');
    Route::get('/quests/create', QuestCreatePlaceholderController::class)->name('quests.create');
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

    Route::get('/quests/explore', QuestExploreController::class)->name('quests.explore');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
