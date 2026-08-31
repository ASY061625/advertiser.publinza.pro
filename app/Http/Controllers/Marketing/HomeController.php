<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Domain\Catalog\Models\Site;
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
            fn (): int => Site::query()->where('status', 'approved')->count(),
        );

        return inertia('Home', ['siteCount' => $siteCount]);
    }
}
