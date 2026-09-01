<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Domain\Catalog\Models\Website;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $siteCount = Cache::remember(
            'marketing:site-count',
            now()->addHour(),
            fn (): int => Website::query()->where('is_active', true)->count(),
        );

        return inertia('Home', ['siteCount' => $siteCount]);
    }
}
