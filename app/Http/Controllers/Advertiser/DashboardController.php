<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $userId = $request->user()->id;

        return inertia('Dashboard', [
            'stats' => [
                'activeProjects' => Project::query()
                    ->where('user_id', $userId)
                    ->where('status', ProjectStatus::Active)
                    ->count(),
                'postsInProgress' => Post::query()
                    ->where('user_id', $userId)
                    ->whereIn('status', [
                        PostStatus::New,
                        PostStatus::InProgress,
                        PostStatus::ContentReview,
                    ])
                    ->count(),
                'publishedThisMonth' => Post::query()
                    ->where('user_id', $userId)
                    ->whereIn('status', [PostStatus::Posted, PostStatus::Completed])
                    ->where('published_at', '>=', now()->startOfMonth())
                    ->count(),
                'spendThisMonthCents' => (int) Post::query()
                    ->where('user_id', $userId)
                    ->where('status', PostStatus::Completed)
                    ->where('updated_at', '>=', now()->startOfMonth())
                    ->sum('price_cents'),
            ],
        ]);
    }
}
