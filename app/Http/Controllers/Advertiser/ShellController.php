<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\System\Models\ChangelogEntry;
use App\Http\Controllers\Controller;
use App\Support\ShellData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The shell's own endpoints: preference writes, the changelog drawer, the
 * command palette and the poll fallback.
 */
class ShellController extends Controller
{
    /** Persists the sidebar state. The client has already applied it optimistically. */
    public function sidebar(Request $request): JsonResponse
    {
        $request->validate(['collapsed' => ['required', 'boolean']]);

        $request->user()->forceFill(['sidebar_collapsed' => $request->boolean('collapsed')])->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Badge counts, polled every 60 seconds when no broadcaster is connected.
     *
     * Counts only: the poll must stay cheap enough to run on every open tab,
     * and the dropdown contents come with the next page load.
     */
    public function counts(Request $request, ShellData $shell): JsonResponse
    {
        return response()->json($shell->forUser($request->user())['counts']);
    }

    /**
     * The What's new drawer. Opening it marks everything read, which is why
     * this is a POST-shaped read rather than a plain GET.
     */
    public function changelog(Request $request): JsonResponse
    {
        $entries = ChangelogEntry::query()
            ->published()
            ->latest('published_at')
            ->take(20)
            ->get(['id', 'title', 'body', 'category', 'published_at']);

        $lastRead = $request->user()->changelog_read_at;

        $payload = $entries->map(fn (ChangelogEntry $entry): array => [
            'id' => $entry->id,
            'title' => $entry->title,
            'body' => $entry->body,
            'category' => $entry->category,
            'publishedAt' => $entry->published_at?->toIso8601String(),
            'unread' => $lastRead === null || $entry->published_at?->greaterThan($lastRead),
        ])->all();

        // Marked read after the payload is built, so the entries that were
        // unread when the drawer opened still render as unread.
        $request->user()->forceFill(['changelog_read_at' => now()])->save();

        return response()->json(['entries' => $payload]);
    }

    public function whatsNew(Request $request): Response
    {
        $entries = ChangelogEntry::query()->published()->latest('published_at')->paginate(25);

        $request->user()->forceFill(['changelog_read_at' => now()])->save();

        return inertia('WhatsNew', ['entries' => $entries]);
    }
}
