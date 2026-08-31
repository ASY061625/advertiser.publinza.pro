<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\Auth\AdminLoginController;
use App\Http\Controllers\Admin\Auth\TwoFactorController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OverviewController;
use App\Http\Controllers\Admin\PayoutController;
use App\Http\Controllers\Admin\SiteReviewController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes — publinza.pro/asylogin
|--------------------------------------------------------------------------
|
| The group in bootstrap/app.php already applies the domain, the /asylogin
| prefix and the `admin` + `2fa` + `throttle:10,1` middleware stack. The two
| routes below are how an admin satisfies those guards in the first place, so
| they drop the guard they are trying to pass — the throttle stays on.
|
*/

Route::withoutMiddleware(['admin', '2fa'])->group(function (): void {
    Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
    Route::post('/login', [AdminLoginController::class, 'store'])->name('login.store');
});

Route::withoutMiddleware('2fa')->group(function (): void {
    Route::get('/two-factor', [TwoFactorController::class, 'create'])->name('two-factor');
    Route::post('/two-factor', [TwoFactorController::class, 'store'])->name('two-factor.store');
});

Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');

Route::get('/', OverviewController::class)->name('overview');

Route::get('/sites', [SiteReviewController::class, 'index'])->name('sites.index');
Route::get('/sites/{site}', [SiteReviewController::class, 'show'])->name('sites.show');
Route::post('/sites/{site}/approve', [SiteReviewController::class, 'approve'])->name('sites.approve');
Route::post('/sites/{site}/reject', [SiteReviewController::class, 'reject'])->name('sites.reject');

Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::post('/orders/{order}/refund', [OrderController::class, 'refund'])->name('orders.refund');

Route::get('/payouts', [PayoutController::class, 'index'])->name('payouts.index');
Route::post('/payouts/{payout}/release', [PayoutController::class, 'release'])->name('payouts.release');

Route::get('/users', [UserController::class, 'index'])->name('users.index');
Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
