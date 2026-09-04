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
use App\Http\Controllers\Advertiser\CheckoutController;
use App\Http\Controllers\Advertiser\CompetitorController;
use App\Http\Controllers\Advertiser\DashboardController;
use App\Http\Controllers\Advertiser\ExportController;
use App\Http\Controllers\Advertiser\MessageController;
use App\Http\Controllers\Advertiser\PostController;
use App\Http\Controllers\Advertiser\PostGridController;
use App\Http\Controllers\Advertiser\ProjectController;
use App\Http\Controllers\Advertiser\ProjectFolderController;
use App\Http\Controllers\Advertiser\SearchController;
use App\Http\Controllers\Advertiser\ShellController;
use App\Http\Controllers\Advertiser\SiteListController;
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

    // One canonical address for the landing screen. The app root redirects to
    // it rather than rendering a second copy under a second route name, so
    // route('dashboard') and the URL an advertiser bookmarks are the same page.
    Route::redirect('/', '/dashboard');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    // One aggregated endpoint behind every widget, cached 5 minutes per
    // user, range, granularity and project scope.
    Route::get('/dashboard/metrics', [DashboardController::class, 'metrics'])->name('dashboard.metrics');

    Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');

    /*
    | One address, two renderings. Asked for as JSON it answers the drawer;
    | visited directly it renders the whole website as a page. That is what
    | makes a drawer deep-linkable without building it twice.
    |
    | Above the /catalog/{anything} shapes below it, so "website" is never read
    | as a slug.
    */
    Route::get('/catalog/website/{website}', [CatalogController::class, 'show'])->name('catalog.website');

    // Reporting a problem with a site. Throttled: it opens a conversation.
    Route::post('/sites/{website}/report', [SiteListController::class, 'report'])
        ->middleware('throttle:10,1')
        ->name('sites.report');

    Route::post('/catalog/view', [CatalogController::class, 'view'])->name('catalog.view');

    /*
    | The three lists an advertiser keeps about a site. Favourites and the
    | blacklist toggle, because both are driven from one control on a row.
    */
    Route::post('/sites/{website}/favorite', [SiteListController::class, 'toggleFavorite'])
        ->name('sites.favorite');
    Route::post('/sites/{website}/blacklist', [SiteListController::class, 'toggleBlacklist'])
        ->name('sites.blacklist');
    Route::post('/sites/{website}/wishlist', [SiteListController::class, 'addToWishlist'])
        ->name('sites.wishlist');

    /*
    | The cart. Literal segments come before {website} and {item}, or "promo"
    | and "bulk" are resolved as a site slug and a line id.
    */
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/bulk', [CartController::class, 'bulk'])->name('cart.bulk');
    Route::post('/cart/promo', [CartController::class, 'applyPromo'])->name('cart.promo.store');
    Route::delete('/cart/promo', [CartController::class, 'removePromo'])->name('cart.promo.destroy');
    Route::post('/cart/{website}', [CartController::class, 'store'])->name('cart.store');
    Route::patch('/cart/{item}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{item}', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::post('/cart/{item}/dismiss', [CartController::class, 'dismissWarning'])->name('cart.dismiss');

    /*
    | Checkout. The step is a query parameter on /checkout, so a refresh and a
    | back button both land where the buyer was.
    */
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::post('/checkout/{item}/article', [CheckoutController::class, 'saveArticle'])->name('checkout.article.store');
    Route::delete('/checkout/{item}/article', [CheckoutController::class, 'clearArticle'])
        ->name('checkout.article.destroy');
    Route::get('/checkout/{order}', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/{order}/invoice', [CheckoutController::class, 'invoice'])->name('checkout.invoice');

    // Literal segments before the {project} wildcard, or "view" is resolved
    // as a project id.
    Route::patch('/projects/view', [ProjectController::class, 'view'])->name('projects.view');
    Route::patch('/projects/draft', [ProjectController::class, 'saveDraft'])->name('projects.draft.save');
    Route::delete('/projects/draft', [ProjectController::class, 'discardDraft'])->name('projects.draft.discard');

    // Throttled hard: this is the one endpoint that makes the server fetch an
    // address the caller typed, so it is the one worth rate-limiting per user
    // rather than trusting the form to behave.
    Route::post('/projects/preview', [ProjectController::class, 'preview'])
        ->middleware('throttle:20,1')
        ->name('projects.preview');

    Route::resource('projects', ProjectController::class)->except(['edit']);
    Route::post('/projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
    Route::post('/projects/{project}/restore', [ProjectController::class, 'restore'])->name('projects.restore');

    // Answers while someone is still ticking boxes on the settings form, so it
    // is a read that runs on every change — throttled, and a plain count.
    Route::get('/projects/{project}/match-count', [ProjectController::class, 'matchCount'])
        ->middleware('throttle:120,1')
        ->name('projects.match-count');

    // Queued: the response says the job started, not that the file is ready.
    Route::post('/projects/{project}/statistics/export', [ProjectController::class, 'exportStatistics'])
        ->middleware('throttle:20,1')
        ->name('projects.statistics.export');

    // Streamed rather than queued: one query, and a job would put a
    // notification between an advertiser and a file they could already have.
    Route::get('/projects/{project}/history/export', [ProjectController::class, 'exportHistory'])
        ->middleware('throttle:20,1')
        ->name('projects.history.export');

    // The one address a finished export is fetched from. Ownership and the
    // 24-hour window are checked here rather than baked into a signed URL that
    // would outlive both.
    Route::get('/exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');

    /*
    | Competitors live under their project for the same reason folders do: the
    | id is only meaningful inside one, and every action here re-checks that
    | rather than trusting route model binding to have found the right row.
    */
    Route::post('/projects/{project}/competitors', [CompetitorController::class, 'store'])
        ->name('projects.competitors.store');
    Route::patch('/projects/{project}/competitors/{competitor}', [CompetitorController::class, 'update'])
        ->name('projects.competitors.update');
    Route::delete('/projects/{project}/competitors/{competitor}', [CompetitorController::class, 'destroy'])
        ->name('projects.competitors.destroy');

    // Throttled: each refresh is a metered call to a paid vendor API. The
    // 24-hour per-competitor cooldown is the real limit and lives in the
    // action; this is the ceiling on hammering the endpoint itself.
    Route::post('/projects/{project}/competitors/{competitor}/refresh', [CompetitorController::class, 'refresh'])
        ->middleware('throttle:30,1')
        ->name('projects.competitors.refresh');

    // Read on demand by the gap drawer, so a hundred keywords per competitor
    // are not shipped with every render of the tab.
    Route::get('/projects/{project}/competitors/{competitor}/gap-keywords', [CompetitorController::class, 'gapKeywords'])
        ->name('projects.competitors.gap');

    // Folders live under the project they belong to, in the URL and in the
    // authorisation: every action checks the project, so a folder id from
    // another account cannot be reached by guessing it.
    Route::get('/projects/{project}/folders/create', [ProjectFolderController::class, 'create'])
        ->name('projects.folders.create');
    Route::post('/projects/{project}/folders', [ProjectFolderController::class, 'store'])
        ->name('projects.folders.store');
    Route::get('/projects/{project}/folders/{folder}/edit', [ProjectFolderController::class, 'edit'])
        ->name('projects.folders.edit');
    Route::put('/projects/{project}/folders/{folder}', [ProjectFolderController::class, 'update'])
        ->name('projects.folders.update');
    Route::delete('/projects/{project}/folders/{folder}', [ProjectFolderController::class, 'destroy'])
        ->name('projects.folders.destroy');

    Route::get('/posts', [PostController::class, 'index'])->name('posts.index');

    // The grid's own endpoints. These sit above /posts/{post} deliberately —
    // a route with a literal segment must be declared before the wildcard that
    // would otherwise swallow it and try to resolve "export" as a post id.
    Route::get('/posts/export', [PostController::class, 'export'])->name('posts.export');
    Route::post('/posts/bulk', [PostController::class, 'bulk'])->name('posts.bulk');
    Route::post('/posts/views', [PostGridController::class, 'storeView'])->name('posts.views.store');
    Route::delete('/posts/views/{view}', [PostGridController::class, 'destroyView'])->name('posts.views.destroy');
    Route::patch('/posts/columns', [PostGridController::class, 'storeColumns'])->name('posts.columns');

    Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
    Route::get('/posts/{post}/detail', [PostController::class, 'detail'])->name('posts.detail');
    Route::post('/posts/{post}/duplicate', [PostController::class, 'duplicate'])->name('posts.duplicate');
    Route::post('/posts/{post}/approve', [PostController::class, 'approve'])->name('posts.approve');
    Route::post('/posts/{post}/cancel', [PostController::class, 'cancel'])->name('posts.cancel');

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{thread}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{thread}', [MessageController::class, 'store'])->name('messages.store');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/top-up', [BillingController::class, 'topUp'])->name('billing.top-up');
});
