<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Messaging\Actions\MarkThreadRead;
use App\Domain\Messaging\Actions\PostMessage;
use App\Domain\Messaging\DTOs\MessageData;
use App\Domain\Messaging\Models\Thread;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advertiser\StoreMessageRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        return inertia('Messages/Index', [
            'threads' => Thread::query()
                ->whereHas('post.project', fn ($q) => $q->where('user_id', $request->user()->id))
                ->orderByDesc('last_message_at')
                ->paginate(25),
        ]);
    }

    public function show(Thread $thread, MarkThreadRead $markThreadRead): Response
    {
        $this->authorize('view', $thread);

        $markThreadRead->handle($thread, 'user');

        return inertia('Messages/Show', ['thread' => $thread->load('messages')]);
    }

    public function store(StoreMessageRequest $request, Thread $thread, PostMessage $postMessage): RedirectResponse
    {
        $this->authorize('reply', $thread);

        $postMessage->handle($thread, new MessageData(
            body: $request->string('body')->value(),
            authorType: 'user',
            authorId: $request->user()->id,
        ));

        return back();
    }
}
