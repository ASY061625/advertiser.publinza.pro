<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Catalog\Models\Website;
use App\Domain\Identity\Enums\NotificationChannel;
use App\Domain\Identity\Enums\NotificationEvent;
use App\Domain\Identity\Support\NotificationSettings;
use App\Domain\Messaging\Actions\MarkThreadRead;
use App\Domain\Messaging\Actions\MarkThreadUnread;
use App\Domain\Messaging\Actions\PostMessage;
use App\Domain\Messaging\Actions\StartConversation;
use App\Domain\Messaging\DTOs\MessageData;
use App\Domain\Messaging\Enums\ConversationStatus;
use App\Domain\Messaging\Enums\SenderType;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\MessageAttachment;
use App\Domain\Messaging\Support\ConversationPresenter;
use App\Domain\Messaging\Support\SupportAvailability;
use App\Domain\Posts\Models\Post;
use App\Events\ShellCountsChanged;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The advertiser's messaging with the Publinza team.
 *
 * Every thread is advertiser ↔ Publinza — there is no third party, because
 * Publinza owns every site in the catalog. What a thread needs instead of
 * participants is a *subject*: which site, or which placement. That is what the
 * list is organised by and what the context panel reads.
 */
class ConversationController extends Controller
{
    /** Per file. Enough for a screenshot or a signed PDF, not a video. */
    private const MAX_UPLOAD_KB = 10 * 1024;

    private const MAX_FILES = 5;

    private const TABS = ['all', 'unread', 'open', 'closed'];

    public function index(
        Request $request,
        ConversationPresenter $presenter,
        MarkThreadRead $markRead,
        SupportAvailability $availability,
        NotificationSettings $settings,
    ): Response {
        $user = $request->user();

        $tab = in_array($request->string('tab')->value(), self::TABS, true)
            ? $request->string('tab')->value()
            : 'all';

        $search = trim($request->string('q')->value());
        $search = $search === '' ? null : $search;

        $threads = $this->query($user, $tab, $search)->get();

        $open = $this->selected($user, $request->integer('thread') ?: null, $threads);

        if ($open !== null) {
            $this->authorize('view', $open);

            // Opening a thread reads it. Doing this before the payload is built
            // means the row's own unread pill and the header badge agree with
            // the messages the reader is looking at, rather than clearing on
            // the next navigation.
            if ($markRead->handle($open, SenderType::User) > 0) {
                ShellCountsChanged::dispatch($user, ['conversations']);
            }

            $open->load(['messages.attachments']);
        }

        return inertia('Conversations/Index', [
            'threads' => $threads->map(fn (Conversation $c): array => $presenter->row($c))->values()->all(),
            'thread' => $open === null ? null : $presenter->thread($open),
            'context' => $open === null ? null : $presenter->context($this->loadContext($open)),
            'filters' => ['tab' => $tab, 'q' => $search, 'thread' => $open?->id],
            'counts' => $this->counts($user),
            'availability' => $availability->state(),
            // From the notification matrix, which is the one place that
            // decides what reaches somebody — not a column of its own.
            'notifyReplies' => $settings->wants($user, NotificationEvent::NewMessage, NotificationChannel::Email),
            'cannedResponses' => $this->cannedResponses(),
        ]);
    }

    /**
     * The websites and posts a new thread can be about.
     *
     * Only what the advertiser has actually bought or saved: a picker over the
     * whole catalog would be a second catalog, and a question about a site
     * nobody has ordered on is a question for the catalog's own report button.
     */
    public function options(Request $request): JsonResponse
    {
        $user = $request->user();

        $posts = Post::query()
            ->where('user_id', $user->id)
            ->with('website:id,domain')
            ->latest('id')
            ->take(200)
            ->get(['id', 'website_id', 'status', 'anchor_text']);

        return response()->json([
            'posts' => $posts->map(fn (Post $post): array => [
                'id' => $post->id,
                'domain' => $post->website?->domain ?? '',
                'label' => sprintf(
                    '#%d · %s · %s',
                    $post->id,
                    $post->website?->domain ?? 'no site',
                    $post->status->label(),
                ),
            ])->values()->all(),
            'websites' => Website::query()
                ->whereIn('id', $posts->pluck('website_id')->filter()->unique())
                ->orderBy('domain')
                ->get(['id', 'domain'])
                ->map(fn (Website $site): array => ['id' => $site->id, 'domain' => $site->domain])
                ->values()
                ->all(),
        ]);
    }

    public function store(Request $request, StartConversation $start): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'min:3', 'max:190'],
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'website_id' => ['nullable', 'integer'],
            'post_id' => ['nullable', 'integer'],
        ] + $this->attachmentRules());

        $user = $request->user();

        // Scoped to the caller on the way in, not trusted from the form. A
        // thread filed against somebody else's post would show them nothing and
        // show us the wrong context.
        $post = isset($data['post_id'])
            ? Post::query()->where('id', $data['post_id'])->where('user_id', $user->id)->first()
            : null;

        $website = isset($data['website_id'])
            ? Website::query()->find($data['website_id'])
            : null;

        $conversation = $start->handle(
            user: $user,
            subject: $data['subject'],
            body: $data['body'],
            website: $website,
            post: $post,
            attachments: $this->files($request),
        );

        return redirect("/conversations?thread={$conversation->id}")
            ->with('success', 'Conversation started.');
    }

    /**
     * Posts one message.
     *
     * Answers 204 to a JSON caller and a redirect to a form post. The composer
     * sends optimistically and has to tell a rejected message from a delivered
     * one, which means it needs a status code — a 302 into a full page render
     * would be a second page of HTML fetched to learn one bit.
     */
    public function reply(Request $request, Conversation $thread, PostMessage $post): RedirectResponse|JsonResponse
    {
        $this->authorize('reply', $thread);

        $data = $request->validate([
            // Nullable, because a message may be nothing but a file. The rule
            // below is what stops an empty send.
            'body' => ['nullable', 'string', 'max:5000'],
            'client_token' => ['nullable', 'string', 'max:40'],
        ] + $this->attachmentRules());

        $files = $this->files($request);

        if (trim((string) ($data['body'] ?? '')) === '' && $files === []) {
            // Thrown rather than redirected with errors, so a JSON caller gets
            // a 422 it can read. `back()->withErrors()` answers a 302 to every
            // caller, and the composer would show a rejected message as a
            // network failure with a Retry that can only fail again.
            throw ValidationException::withMessages(['body' => 'Write something, or attach a file.']);
        }

        $post->handle($thread, new MessageData(
            body: (string) ($data['body'] ?? ''),
            senderType: SenderType::User,
            senderId: $request->user()->id,
            clientToken: $data['client_token'] ?? null,
            attachments: $files,
        ));

        return $request->wantsJson() ? response()->json(null, 204) : back();
    }

    public function markUnread(Request $request, Conversation $thread, MarkThreadUnread $unread): RedirectResponse
    {
        $this->authorize('view', $thread);

        if (! $unread->handle($thread)) {
            return back()->with('error', 'Nothing to mark unread — the team has not replied yet.');
        }

        ShellCountsChanged::dispatch($request->user(), ['conversations']);

        // Away from the thread, not back into it: leaving the reader inside a
        // thread they just marked unread means the next page load reads it
        // again, and the menu item appears to do nothing.
        return redirect('/conversations')->with('success', 'Marked unread.');
    }

    public function updateStatus(Request $request, Conversation $thread): RedirectResponse
    {
        $this->authorize('view', $thread);

        $data = $request->validate(['status' => ['required', 'in:open,closed']]);
        $status = ConversationStatus::from($data['status']);

        $thread->update(['status' => $status]);

        return back()->with(
            'success',
            $status === ConversationStatus::Closed ? 'Conversation closed.' : 'Conversation reopened.',
        );
    }

    public function updateMute(Request $request, Conversation $thread): RedirectResponse
    {
        $this->authorize('view', $thread);

        $muted = $request->boolean('muted');

        $thread->update(['muted_at' => $muted ? now() : null]);

        return back()->with(
            'success',
            $muted ? 'Muted — this thread will not email you.' : 'Notifications back on for this thread.',
        );
    }

    /**
     * Serves an attachment through the app rather than from a public URL.
     *
     * The file lives on the private disk for the same reason an unpublished
     * article does: a guessable link would hand somebody else's invoice or
     * screenshot to anyone who tried the next id.
     */
    public function attachment(Request $request, MessageAttachment $attachment): StreamedResponse
    {
        $attachment->load('message.conversation');

        $conversation = $attachment->message?->conversation;

        abort_if($conversation === null, 404);
        $this->authorize('view', $conversation);

        $disk = Storage::disk($attachment->disk);

        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name);
    }

    // ------------------------------------------------------------- internals

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Conversation>
     */
    private function query(User $user, string $tab, ?string $search): Builder
    {
        return Conversation::query()
            ->where('user_id', $user->id)
            ->with(ConversationPresenter::WITH)
            // The last message only, for the row's excerpt. `messages()` is
            // ordered oldest-first, so this reverses it and takes one — the
            // presenter reads ->last() off whatever this loaded.
            ->with(['messages' => fn ($q) => $q->reorder()->latest('created_at')->latest('id')->limit(1)])
            ->with(['messages.attachments'])
            ->withCount(['messages as unread_count' => fn ($q) => $q
                ->whereNull('read_at')
                ->where('sender_type', '!=', SenderType::User->value)])
            ->when($tab === 'open', fn ($q) => $q->where('status', ConversationStatus::Open))
            ->when($tab === 'closed', fn ($q) => $q->where('status', ConversationStatus::Closed))
            ->when($tab === 'unread', fn ($q) => $q->whereHas('messages', fn ($m) => $m
                ->whereNull('read_at')
                ->where('sender_type', '!=', SenderType::User->value)))
            ->when($search !== null, fn ($q) => $q->where(fn ($group) => $group
                ->where('subject', 'like', "%{$search}%")
                ->orWhereHas('messages', fn ($m) => $m->where('body', 'like', "%{$search}%"))
                ->orWhereHas('website', fn ($w) => $w->where('domain', 'like', "%{$search}%"))))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');
    }

    /**
     * Which thread the right-hand pane is showing.
     *
     * A thread named in the URL wins even when the current tab filters it out —
     * a link to a closed thread from an email has to open it, not land on an
     * empty pane because the reader's last tab was "Open".
     *
     * @param  Collection<int, Conversation>  $threads
     */
    private function selected(User $user, ?int $id, $threads): ?Conversation
    {
        if ($id === null) {
            return null;
        }

        return $threads->firstWhere('id', $id)
            ?? Conversation::query()->with(ConversationPresenter::WITH)->find($id);
    }

    /**
     * The context panel's own relations, loaded only for the open thread.
     *
     * The website is *re-fetched* rather than ->load()ed onto the one the list
     * already hydrated. That one was selected down to four columns for the row,
     * and ->load() adds relations, never the missing columns — so the panel
     * would read every term off a model that does not have them and print
     * nulls, which is worse than an error because it looks like data.
     */
    private function loadContext(Conversation $conversation): Conversation
    {
        if ($conversation->post !== null) {
            $conversation->post->load(['website:id,domain', 'articles:id,post_id', 'statusHistory']);

            return $conversation;
        }

        if ($conversation->website_id !== null) {
            $conversation->setRelation('website', Website::query()
                ->with(['latestMetric', 'category:id,name', 'country:id,name', 'primaryLanguage:id,name'])
                ->find($conversation->website_id));
        }

        return $conversation;
    }

    /**
     * @return array{all: int, unread: int, open: int, closed: int}
     */
    private function counts(User $user): array
    {
        $base = fn (): Builder => Conversation::query()->where('user_id', $user->id);

        return [
            'all' => $base()->count(),
            'unread' => $base()->whereHas('messages', fn ($m) => $m
                ->whereNull('read_at')
                ->where('sender_type', '!=', SenderType::User->value))->count(),
            'open' => $base()->where('status', ConversationStatus::Open)->count(),
            'closed' => $base()->where('status', ConversationStatus::Closed)->count(),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function attachmentRules(): array
    {
        return [
            'attachments' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'attachments.*' => [
                'file',
                'mimes:pdf,doc,docx,png,jpg,jpeg,zip',
                'max:'.self::MAX_UPLOAD_KB,
            ],
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    private function files(Request $request): array
    {
        $files = $request->file('attachments');

        return is_array($files) ? array_values($files) : [];
    }

    /**
     * The composer's canned openers.
     *
     * Server-side rather than hard-coded in the component, because these are
     * copy: they get rewritten by whoever answers them, and that person should
     * not need a frontend deploy to do it.
     *
     * @return list<array{id: string, label: string, body: string}>
     */
    private function cannedResponses(): array
    {
        return [
            [
                'id' => 'publication-update',
                'label' => 'Request a publication update',
                'body' => "Could you let me know where this one stands? I'd like to know roughly when it will go live so I can plan the rest of the campaign around it.",
            ],
            [
                'id' => 'content-guidelines',
                'label' => 'Ask about content guidelines',
                'body' => "Before I send the draft — could you confirm the guidelines for this site? I'm mainly after the word count, how many links are allowed, and anything the editors usually send back.",
            ],
            [
                'id' => 'link-placement',
                'label' => 'Ask about link placement',
                'body' => 'Where in the article will the link sit, and will it be dofollow? I want to make sure the anchor reads naturally in that position.',
            ],
            [
                'id' => 'revision',
                'label' => 'Request a revision',
                'body' => "There's something I'd like changed in the published article. Details below — could you let me know if that's possible and how long it usually takes?",
            ],
        ];
    }
}
