<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\System\Enums\ChangelogType;
use App\Domain\System\Models\ChangelogEntry;
use App\Domain\System\Support\ChangelogHtml;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What's new: the drawer, the full page, the images and the major-release
 * acknowledgement.
 *
 * Split out of ShellController, which was carrying it alongside sidebar
 * preferences and the command palette. The changelog is its own surface now —
 * three routes, a filter and an announcement — and it had outgrown being one
 * method on the shell.
 */
class ChangelogController extends Controller
{
    /** What the drawer shows. Ten, as specified; the page has the rest. */
    private const DRAWER_LIMIT = 10;

    /**
     * The drawer. Opening it marks everything seen, which is why a read is
     * shaped like a write.
     */
    public function drawer(Request $request): JsonResponse
    {
        $user = $request->user();
        $lastSeen = $user->last_seen_changelog_at;

        $entries = ChangelogEntry::query()
            ->published()
            ->latest('published_at')
            ->take(self::DRAWER_LIMIT)
            ->get();

        $payload = $entries->map(fn (ChangelogEntry $entry): array => $this->present($entry, $lastSeen))->all();

        /*
         * Stamped after the payload is built, so the entries that were unseen
         * when the drawer opened still render with their dot. Stamping first
         * would mean the drawer never shows anything as new — which is the one
         * thing it exists to do.
         */
        $user->forceFill(['last_seen_changelog_at' => now()])->save();

        return response()->json(['entries' => $payload]);
    }

    /**
     * The full changelog, grouped by month and filterable by type.
     *
     * Not paginated: this is a product changelog, not a feed. Grouping by month
     * only works if the months are all there, and "load more" on a page whose
     * whole job is being scannable is a way to hide the history.
     */
    public function index(Request $request): Response
    {
        $request->validate(['type' => ['nullable', 'in:new,improved,fixed']]);

        $filter = $request->string('type')->toString();
        $type = $filter === '' ? null : ChangelogType::from($filter);

        $entries = ChangelogEntry::query()
            ->published()
            ->when($type !== null, fn ($q) => $q->where('type', $type->value))
            ->latest('published_at')
            ->get();

        $lastSeen = $request->user()->last_seen_changelog_at;

        $request->user()->forceFill(['last_seen_changelog_at' => now()])->save();

        return inertia('WhatsNew', [
            'type' => $type?->value,
            'months' => $this->byMonth($entries, $lastSeen),
            'counts' => $this->counts(),
        ]);
    }

    /**
     * "Got it" on the major-release modal.
     *
     * Its own timestamp: opening the drawer clears the unseen dot for
     * everything, and it must not also dismiss an announcement the person never
     * saw. Two different acknowledgements, two different columns.
     */
    public function acknowledge(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['changelog_major_ack_at' => now()])->save();

        return back();
    }

    /**
     * A changelog image.
     *
     * Served through the app rather than from a public disk: these are product
     * screenshots that go up before a release, and a guessable storage URL is
     * how an unpublished entry's screenshot leaks.
     */
    public function image(ChangelogEntry $entry): StreamedResponse
    {
        abort_if($entry->image_path === null, 404);
        abort_if($entry->published_at === null || $entry->published_at->isFuture(), 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($entry->image_path), 404);

        return $disk->response($entry->image_path);
    }

    /**
     * @param  Collection<int, ChangelogEntry>  $entries
     * @return list<array<string, mixed>>
     */
    private function byMonth(Collection $entries, ?Carbon $lastSeen): array
    {
        return $entries
            // Keyed on the sortable form and labelled separately, so December
            // and January of two different years cannot land in one bucket.
            ->groupBy(fn (ChangelogEntry $entry): string => $entry->published_at?->format('Y-m') ?? '0000-00')
            ->map(fn ($group, string $key): array => [
                'key' => $key,
                'label' => $group->first()->published_at?->format('F Y') ?? 'Undated',
                'entries' => $group->map(fn (ChangelogEntry $entry): array => $this->present($entry, $lastSeen))->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * How many of each type exist, for the filter's counts.
     *
     * @return array<string, int>
     */
    private function counts(): array
    {
        $rows = ChangelogEntry::query()
            ->published()
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $counts = ['all' => (int) $rows->sum()];

        foreach (ChangelogType::cases() as $case) {
            $counts[$case->value] = (int) ($rows[$case->value] ?? 0);
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ChangelogEntry $entry, ?Carbon $lastSeen): array
    {
        return [
            'id' => $entry->id,
            'slug' => $entry->slug,
            'anchor' => $entry->anchor(),
            'title' => $entry->title,
            'body' => ChangelogHtml::clean($entry->body),
            'type' => $entry->type->value,
            'typeLabel' => $entry->type->label(),
            'isMajor' => $entry->is_major,
            'imageUrl' => $entry->imageUrl(),
            'publishedAt' => $entry->published_at?->toIso8601String(),
            'unread' => $lastSeen === null || ($entry->published_at?->greaterThan($lastSeen) ?? false),
        ];
    }
}
