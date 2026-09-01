<?php

declare(strict_types=1);

use App\Http\Controllers\Advertiser\Auth\EmailVerificationController;
use App\Http\Controllers\Advertiser\Auth\LoginController;
use App\Http\Controllers\Advertiser\Auth\PasswordResetController;
use App\Http\Controllers\Advertiser\Auth\SignupController;
use App\Http\Controllers\Advertiser\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Advertiser\Auth\TwoFactorSettingsController;
use App\Http\Controllers\Advertiser\BillingController;
use App\Http\Controllers\Advertiser\CartController;
use App\Http\Controllers\Advertiser\CatalogController;
use App\Http\Controllers\Advertiser\DashboardController;
use App\Http\Controllers\Advertiser\MessageController;
use App\Http\Controllers\Advertiser\PostController;
use App\Http\Controllers\Advertiser\ProjectController;
use App\Http\Controllers\Advertiser\SearchController;
use App\Http\Controllers\Advertiser\ShellController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Advertiser routes — app.publinza.pro
|--------------------------------------------------------------------------
|
| Guarded by the `web` guard. Rendered into resources/views/app.blade.php, so
| the admin bundle is never referenced from this surface.
|
*/

/*
 * The design system gallery. Registered outside every guard so it can be opened
 * without an account, and never registered in production at all.
 */
if (! app()->isProduction()) {
    Route::get('/design-system', fn () => inertia('DesignSystem'))->name('design-system');
}

/*
 * Advertisers only. There is no publisher role and no publisher signup anywhere
 * in this product — every account created here buys placements, and the sites
 * being bought are ours.
 */
Route::middleware('guest')->group(function (): void {
    Route::get('/signup', [SignupController::class, 'create'])->name('signup');
    Route::post('/signup', [SignupController::class, 'store'])->middleware('throttle:10,60');

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    // A coarse per-IP ceiling. The real control is LoginThrottle, which is
    // keyed on email and IP together and locks out for 15 minutes.
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');

    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:5,10')
        ->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:10,60')
        ->name('password.update');

    // Not authenticated yet: the pending user id is in the session, and only a
    // passing challenge turns it into a sign-in.
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
        ->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
        ->middleware('throttle:20,1');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Reachable while unverified: this is where an unverified account lands.
    Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])
        ->name('verification.resend');

    /*
     * The app shell's own endpoints. Available to any signed-in advertiser,
     * verified or not, because the shell frames the verification notice too.
     */
    Route::patch('/shell/sidebar', [ShellController::class, 'sidebar'])->name('shell.sidebar');
    Route::get('/shell/counts', [ShellController::class, 'counts'])->name('shell.counts');
    Route::get('/shell/changelog', [ShellController::class, 'changelog'])->name('shell.changelog');
    Route::get('/whats-new', [ShellController::class, 'whatsNew'])->name('whats-new');
    Route::get('/search', SearchController::class)->middleware('throttle:60,1')->name('search');

    Route::get('/settings/two-factor', [TwoFactorSettingsController::class, 'show'])->name('two-factor.show');
    Route::post('/settings/two-factor', [TwoFactorSettingsController::class, 'enable'])->name('two-factor.enable');
    Route::post('/settings/two-factor/confirm', [TwoFactorSettingsController::class, 'confirm'])
        ->name('two-factor.confirm');
    Route::post('/settings/two-factor/recovery-codes', [TwoFactorSettingsController::class, 'regenerateRecoveryCodes'])
        ->name('two-factor.recovery-codes');
    Route::delete('/settings/two-factor', [TwoFactorSettingsController::class, 'disable'])->name('two-factor.disable');
});

/*
 * Everything that spends money or creates work needs a confirmed address.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::get('/catalog/{site}', [CatalogController::class, 'show'])->name('catalog.show');

    Route::post('/cart/{website}', [CartController::class, 'store'])->name('cart.store');
    Route::delete('/cart/{item}', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::post('/cart/checkout', [CartController::class, 'checkout'])->name('cart.checkout');

    Route::resource('projects', ProjectController::class)->except(['destroy']);
    Route::post('/projects/{project}/publish', [ProjectController::class, 'publish'])->name('projects.publish');

    Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
    Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
    Route::post('/posts/{post}/approve', [PostController::class, 'approve'])->name('posts.approve');
    Route::post('/posts/{post}/cancel', [PostController::class, 'cancel'])->name('posts.cancel');

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{thread}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{thread}', [MessageController::class, 'store'])->name('messages.store');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/top-up', [BillingController::class, 'topUp'])->name('billing.top-up');
});
