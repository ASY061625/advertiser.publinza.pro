<?php

declare(strict_types=1);

use App\Http\Controllers\Marketing\BlogController;
use App\Http\Controllers\Marketing\CatalogPreviewController;
use App\Http\Controllers\Marketing\ContactController;
use App\Http\Controllers\Marketing\HomeController;
use App\Http\Controllers\Marketing\LegalController;
use App\Http\Controllers\Marketing\PageController;
use App\Http\Controllers\Marketing\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marketing routes — publinza.pro
|--------------------------------------------------------------------------
|
| Public and indexable. Server-rendered Blade rather than Inertia: these pages
| are content, they need to arrive as HTML for crawlers and for LCP, and they
| ship one small progressive-enhancement script instead of a framework.
|
*/

Route::get('/', HomeController::class)->name('home');
Route::get('/catalog', CatalogPreviewController::class)->name('catalog');

Route::get('/how-it-works', [PageController::class, 'howItWorks'])->name('how-it-works');
Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing');
Route::get('/about', [PageController::class, 'about'])->name('about');

Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])
    // Public and unauthenticated, so it is the obvious spam target on the site.
    ->middleware('throttle:5,10')
    ->name('contact.store');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{post:slug}', [BlogController::class, 'show'])->name('blog.show');

Route::get('/terms', [LegalController::class, 'terms'])->name('terms');
Route::get('/privacy', [LegalController::class, 'privacy'])->name('privacy');
Route::get('/refund-policy', [LegalController::class, 'refunds'])->name('refunds');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
