<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Catalog\Models\Country;
use App\Domain\Catalog\Models\Language;
use App\Domain\Catalog\Models\WebsiteCategory;
use App\Domain\Posts\Actions\ApproveDraft;
use App\Domain\Posts\Actions\BulkPostActions;
use App\Domain\Posts\Actions\CancelPost;
use App\Domain\Posts\Actions\DuplicatePost;
use App\Domain\Posts\Actions\ExportPostsCsv;
use App\Domain\Posts\Actions\GetPostDetail;
use App\Domain\Posts\Actions\ListPosts;
use App\Domain\Posts\DTOs\PostFilters;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Enums\PostTab;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use App\Http\Controllers\Controller;
use App\Support\PostGridPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PostController extends Controller
{
    public function index(Request $request, ListPosts $list): Response
    {
        $user = $request->user();
        $filters = PostFilters::fromRequest($request);

        return inertia('Posts/Index', [
            'posts' => $list->paginate($user, $filters)->through($this->row(...)),
            'tabCounts' => $list->tabCounts($user, $filters),
            'filters' => $filters->toQuery(),

            // Whether the account has any posts at all, regardless of filters.
            // "You have not made a post yet" and "nothing matches these
            // filters" are different situations and get different screens.
            'hasAnyPosts' => Post::query()->where('user_id', $user->id)->exists(),
            'isFiltering' => $filters->isFiltering(),

            'options' => $this->options($user),
            'columns' => PostGridPreferences::for($user),
            'savedViews' => $user->savedViews()->where('surface', 'posts')
                ->get(['id', 'name', 'query'])->all(),
        ]);
    }

    /** The row drawer's payload. Also what `/posts?post={id}` deep-links to. */
    public function detail(Request $request, Post $post, GetPostDetail $detail): JsonResponse
    {
        $this->authorize('view', $post);

        return response()->json($detail->handle($post));
    }

    public function export(Request $request, ExportPostsCsv $export): StreamedResponse
    {
        $ids = $this->ids($request);

        return $export->handle(
            $request->user(),
            PostFilters::fromRequest($request),
            $ids === [] ? null : $ids,
        );
    }

    public function bulk(Request $request, BulkPostActions $bulk): RedirectResponse|StreamedResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:cancel,move,download'],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'reason' => ['required_if:action,cancel', 'nullable', 'string', 'min:3', 'max:500'],
            'folder_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        /** @var list<int> $ids */
        $ids = array_map('intval', $validated['ids']);

        try {
            return match ($validated['action']) {
                'download' => $bulk->downloadArticles($user, $ids),
                'cancel' => $this->flashCancelled($bulk->cancelMany($user, $ids, (string) $validated['reason'])),
                default => $this->flashMoved($bulk->moveToFolder(
                    $user,
                    $ids,
                    isset($validated['folder_id']) ? (int) $validated['folder_id'] : null,
                )),
            };
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function duplicate(Request $request, Post $post, DuplicatePost $duplicate): RedirectResponse
    {
        $this->authorize('view', $post);

        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'folder_id' => ['nullable', 'integer'],
        ]);

        try {
            $copy = $duplicate->handle(
                $request->user(),
                $post,
                (int) $validated['project_id'],
                isset($validated['folder_id']) ? (int) $validated['folder_id'] : null,
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Duplicated as draft #{$copy->id}.");
    }

    /**
     * One post has one address, and it is the grid with its drawer open.
     *
     * A separate detail page would be a second place the same information
     * lives and a second thing to keep in step; the drawer already shows
     * everything and keeps the reader's place in a filtered list. This route
     * stays so older links still work — it redirects to the drawer.
     */
    public function show(Post $post): RedirectResponse
    {
        $this->authorize('view', $post);

        return redirect()->route('posts.index', ['post' => $post->id]);
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

    /**
     * The grid row. Deliberately flat and deliberately small: this is sent 100
     * times per page, so it carries what the columns render and nothing else.
     *
     * @return array<string, mixed>
     */
    private function row(Post $post): array
    {
        return [
            'id' => $post->id,
            'domain' => $post->website?->domain ?? '',
            'dr' => $post->website?->latestMetric?->ahrefs_dr,
            'traffic' => $post->website?->latestMetric?->monthly_traffic,
            'project' => $post->project?->name,
            'projectId' => $post->project_id,
            'folder' => $post->folder?->name,
            'anchorText' => $post->anchor_text,
            'targetUrl' => $post->target_url,
            'status' => $post->status->value,
            'statusLabel' => $post->status->label(),
            'badge' => $post->status->badgeKey(),
            'canCancel' => $post->status->isPrePosted(),
            'priceCents' => $post->price_cents,
            'createdAt' => $post->created_at?->toIso8601String(),
            'publishedAt' => $post->published_at?->toIso8601String(),
            'deadlineAt' => $post->deadline_at?->toIso8601String(),
            'publishedUrl' => $post->published_url,
        ];
    }

    /**
     * The filter bar's option lists.
     *
     * Projects and folders are the advertiser's own. Categories, countries and
     * languages are narrowed to those that actually appear on their posts —
     * a filter that can only ever return nothing is noise.
     *
     * @return array<string, mixed>
     */
    private function options(mixed $user): array
    {
        $websiteIds = Post::query()->where('user_id', $user->id)->select('website_id');

        $used = fn (string $column, string $model) => $model::query()
            ->whereIn('id', fn ($q) => $q->select($column)->from('websites')->whereIn('id', $websiteIds))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();

        return [
            'projects' => Project::query()
                ->where('user_id', $user->id)
                ->with('folders:id,project_id,name')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
            'statuses' => array_map(static fn (PostStatus $s): array => [
                'value' => $s->value,
                'label' => $s->label(),
                'badge' => $s->badgeKey(),
            ], PostStatus::cases()),
            'tabs' => array_map(static fn (PostTab $t): array => [
                'value' => $t->value,
                'label' => $t->label(),
                'badge' => $t->badgeKey(),
            ], PostTab::cases()),
            'categories' => $used('category_id', WebsiteCategory::class),
            'countries' => $used('country_id', Country::class),
            'languages' => $used('primary_language_id', Language::class),
        ];
    }

    /**
     * @return list<int>
     */
    private function ids(Request $request): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            (array) $request->input('ids', []),
        )));
    }

    /**
     * @param  array{cancelled: int, skipped: int}  $result
     */
    private function flashCancelled(array $result): RedirectResponse
    {
        $message = sprintf(
            '%d post%s cancelled.',
            $result['cancelled'],
            $result['cancelled'] === 1 ? '' : 's',
        );

        // Say what was skipped and why, rather than reporting a smaller number
        // than the selection and leaving the difference unexplained.
        if ($result['skipped'] > 0) {
            $message .= sprintf(
                ' %d could not be — a post can only be cancelled before it goes live.',
                $result['skipped'],
            );
        }

        return back()->with('success', $message);
    }

    /**
     * @param  array{moved: int, skipped: int}  $result
     */
    private function flashMoved(array $result): RedirectResponse
    {
        $message = sprintf('%d post%s moved.', $result['moved'], $result['moved'] === 1 ? '' : 's');

        if ($result['skipped'] > 0) {
            $message .= sprintf(
                ' %d stayed put — a folder only holds posts from its own project.',
                $result['skipped'],
            );
        }

        return back()->with('success', $message);
    }
}
