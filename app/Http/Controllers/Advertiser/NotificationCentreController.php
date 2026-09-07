<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Notifications\Support\NotificationCentre;
use App\Events\ShellCountsChanged;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The notification drawer's endpoints.
 *
 * JSON rather than Inertia: the drawer opens over whatever page you are on, and
 * a full Inertia visit to fetch its contents would remount that page underneath
 * it. Only `index` here is a page, and only so the drawer has a URL to fall
 * back to for somebody who arrives at /notifications from an email.
 */
class NotificationCentreController extends Controller
{
    public function list(Request $request, NotificationCentre $centre): JsonResponse
    {
        $request->validate([
            'filter' => ['nullable', 'in:all,unread'],
            'page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json($centre->forUser(
            $request->user(),
            $request->string('filter')->toString() === 'unread',
            max(1, (int) $request->integer('page', 1)),
        ));
    }

    /**
     * Marks one notification read.
     *
     * Scoped through the relation, not by id alone: `notifications` is one
     * table for every account, and `where('id', …)` on its own would let anyone
     * mark anyone's notification read.
     */
    public function read(Request $request, string $notification): JsonResponse
    {
        $row = $request->user()->notifications()->whereKey($notification)->first();

        if ($row === null) {
            return response()->json(['ok' => false], 404);
        }

        $row->markAsRead();

        ShellCountsChanged::dispatch($request->user(), ['notifications']);

        return response()->json(['ok' => true]);
    }

    /**
     * Marks several read at once — what an expanded group's items need when
     * the group itself is clicked.
     */
    public function readMany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['string', 'max:64'],
        ]);

        $request->user()->unreadNotifications()->whereIn('id', $data['ids'])->update(['read_at' => now()]);

        ShellCountsChanged::dispatch($request->user(), ['notifications']);

        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        ShellCountsChanged::dispatch($request->user(), ['notifications']);

        return response()->json(['ok' => true]);
    }

    /**
     * The whole list as a page.
     *
     * Every notification email links here, and a link from an email has to work
     * for somebody who is not signed in yet — after the login redirect they
     * land on a page, not on a drawer that needs a click to open.
     */
    public function index(Request $request, NotificationCentre $centre): Response
    {
        return inertia('Notifications/Index', [
            'filter' => $request->string('filter')->toString() === 'unread' ? 'unread' : 'all',
            'centre' => $centre->forUser(
                $request->user(),
                $request->string('filter')->toString() === 'unread',
            ),
        ]);
    }

    /** The drawer's "Mark all read", from the full page. */
    public function readAllFromPage(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        ShellCountsChanged::dispatch($request->user(), ['notifications']);

        return back()->with('success', 'Everything is marked read.');
    }
}
