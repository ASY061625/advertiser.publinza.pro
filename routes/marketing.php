<?php

declare(strict_types=1);

use App\Http\Controllers\Marketing\HomeController;
use App\Http\Controllers\Marketing\PageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marketing routes — publinza.pro
|--------------------------------------------------------------------------
|
| Public, indexable, no auth. Rendered into resources/views/marketing.blade.php
| so only the marketing bundle ships here.
|
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/how-it-works', [PageController::class, 'howItWorks'])->name('how-it-works');
Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing');
Route::get('/publishers', [PageController::class, 'publishers'])->name('publishers');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
