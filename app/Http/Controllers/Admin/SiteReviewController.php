<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Actions\ReviewSite;
use App\Domain\Admin\DTOs\SiteReviewDecision;
use App\Domain\Catalog\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;

class SiteReviewController extends Controller
{
    public function index(Request $request): Response
    {
        return inertia('Sites/Index', [
            'sites' => Site::query()
                ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
                ->latest()
                ->paginate(50),
        ]);
    }

    public function show(Site $site): Response
    {
        return inertia('Sites/Show', ['site' => $site]);
    }

    public function approve(Site $site, ReviewSite $reviewSite): RedirectResponse
    {
        $this->authorize('reviewSites', Auth::guard('admin')->user());

        $reviewSite->handle($site, Auth::guard('admin')->user(), new SiteReviewDecision(approved: true));

        return back()->with('success', 'Site approved');
    }

    public function reject(Request $request, Site $site, ReviewSite $reviewSite): RedirectResponse
    {
        $this->authorize('reviewSites', Auth::guard('admin')->user());

        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $reviewSite->handle($site, Auth::guard('admin')->user(), new SiteReviewDecision(
            approved: false,
            reason: $request->string('reason')->value(),
        ));

        return back()->with('success', 'Site rejected');
    }
}
