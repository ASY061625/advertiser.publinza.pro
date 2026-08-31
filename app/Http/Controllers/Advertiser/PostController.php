<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Posts\Actions\ApproveDraft;
use App\Domain\Posts\Models\Post;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class PostController extends Controller
{
    public function index(Request $request): Response
    {
        return inertia('Posts/Index', [
            'posts' => Post::query()
                ->whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
                ->with(['site', 'project'])
                ->latest()
                ->paginate(25),
        ]);
    }

    public function show(Post $post): Response
    {
        $this->authorize('view', $post);

        return inertia('Posts/Show', ['post' => $post->load(['site', 'project'])]);
    }

    public function approve(Post $post, ApproveDraft $approveDraft): RedirectResponse
    {
        $this->authorize('approve', $post);

        $approveDraft->handle($post);

        return back()->with('success', 'Draft approved');
    }
}
