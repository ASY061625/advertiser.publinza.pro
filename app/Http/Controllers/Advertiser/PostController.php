<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Posts\Actions\ApproveDraft;
use App\Domain\Posts\Actions\CancelPost;
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
                ->where('user_id', $request->user()->id)
                ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
                ->with(['website', 'project'])
                ->latest()
                ->paginate(25),
        ]);
    }

    public function show(Post $post): Response
    {
        $this->authorize('view', $post);

        return inertia('Posts/Show', [
            'post' => $post->load(['website', 'project', 'statusHistory', 'articles']),
        ]);
    }

    public function approve(Post $post, ApproveDraft $approveDraft): RedirectResponse
    {
        $this->authorize('approve', $post);

        $approveDraft->handle($post);

        return back()->with('success', 'Draft approved');
    }

    public function cancel(Request $request, Post $post, CancelPost $cancelPost): RedirectResponse
    {
        $this->authorize('cancel', $post);

        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $cancelPost->handle($post, $request->string('reason')->value());

        return back()->with('success', 'Cancelled');
    }
}
