<?php

declare(strict_types=1);

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\DTOs\SiteReviewDecision;
use App\Domain\Admin\Models\Admin;
use App\Domain\Catalog\Models\Site;
use Illuminate\Support\Facades\Cache;

final class ReviewSite
{
    public function handle(Site $site, Admin $admin, SiteReviewDecision $decision): Site
    {
        $site->update([
            'status' => $decision->approved ? 'approved' : 'rejected',
            'rejection_reason' => $decision->reason,
            'reviewed_by' => $admin->id,
            'approved_at' => $decision->approved ? now() : null,
        ]);

        // The catalog's quant-bar ranges are derived from approved sites.
        Cache::forget('catalog:ranges');

        return $site->refresh();
    }
}
