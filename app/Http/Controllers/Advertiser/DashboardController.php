<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Posts\DTOs\PostStatus;
use App\Domain\Posts\Models\Post;
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
                    ->whereNot('status', 'draft')
                    ->count(),
                'postsInProgress' => Post::query()
                    ->whereHas('project', fn ($q) => $q->where('user_id', $userId))
                    ->whereIn('status', [PostStatus::InProgress->value, PostStatus::ContentReview->value])
                    ->count(),
                'publishedThisMonth' => Post::query()
                    ->whereHas('project', fn ($q) => $q->where('user_id', $userId))
                    ->where('status', PostStatus::Published->value)
                    ->where('published_at', '>=', now()->startOfMonth())
                    ->count(),
                'spendThisMonthMinorUnits' => 0,
            ],
        ]);
    }
}
