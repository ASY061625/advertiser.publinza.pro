<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Actions\ReviewWebsite;
use App\Domain\Admin\DTOs\SiteReviewDecision;
use App\Domain\Catalog\Models\Website;
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
            'sites' => Website::query()
                ->with(['category', 'latestMetric'])
                ->when($request->boolean('pending'), fn ($q) => $q->where('is_active', false))
                ->latest()
                ->paginate(50),
        ]);
    }

    public function show(Website $website): Response
    {
        return inertia('Sites/Show', [
            'site' => $website->load(['category', 'primaryLanguage', 'country', 'prices', 'metrics']),
        ]);
    }

    public function approve(Website $website, ReviewWebsite $review): RedirectResponse
    {
        $this->authorize('reviewSites', Auth::guard('admin')->user());

        $review->handle($website, Auth::guard('admin')->user(), new SiteReviewDecision(approved: true));

        return back()->with('success', 'Site approved');
    }

    public function reject(Request $request, Website $website, ReviewWebsite $review): RedirectResponse
    {
        $this->authorize('reviewSites', Auth::guard('admin')->user());

        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $review->handle($website, Auth::guard('admin')->user(), new SiteReviewDecision(
            approved: false,
            reason: $request->string('reason')->value(),
        ));

        return back()->with('success', 'Site rejected');
    }
}
