<?php

declare(strict_types=1);

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\DTOs\SiteReviewDecision;
use App\Domain\Admin\Models\Admin;
use App\Domain\Catalog\Models\Website;
use Illuminate\Support\Facades\Cache;

final class ReviewWebsite
{
    public function handle(Website $website, Admin $admin, SiteReviewDecision $decision): Website
    {
        $website->update(['is_active' => $decision->approved]);

        // The catalog's quant-bar ranges are derived from active sites.
        Cache::forget('catalog:ranges');

        return $website->refresh();
    }
}
