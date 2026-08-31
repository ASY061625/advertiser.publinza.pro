<?php

declare(strict_types=1);

use App\Http\Controllers\Advertiser\Auth\LoginController;
use App\Http\Controllers\Advertiser\Auth\RegisterController;
use App\Http\Controllers\Advertiser\BillingController;
use App\Http\Controllers\Advertiser\CartController;
use App\Http\Controllers\Advertiser\CatalogController;
use App\Http\Controllers\Advertiser\DashboardController;
use App\Http\Controllers\Advertiser\MessageController;
use App\Http\Controllers\Advertiser\PostController;
use App\Http\Controllers\Advertiser\ProjectController;
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

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::get('/catalog/{site}', [CatalogController::class, 'show'])->name('catalog.show');

    Route::post('/cart/{site}', [CartController::class, 'store'])->name('cart.store');
    Route::delete('/cart/{item}', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::post('/cart/checkout', [CartController::class, 'checkout'])->name('cart.checkout');

    Route::resource('projects', ProjectController::class)->except(['destroy']);
    Route::post('/projects/{project}/publish', [ProjectController::class, 'publish'])->name('projects.publish');

    Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
    Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
    Route::post('/posts/{post}/approve', [PostController::class, 'approve'])->name('posts.approve');

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{thread}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{thread}', [MessageController::class, 'store'])->name('messages.store');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/top-up', [BillingController::class, 'topUp'])->name('billing.top-up');
});
