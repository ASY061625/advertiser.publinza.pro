<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Http\Controllers\Controller;
use App\Support\ShellData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The shell's own endpoints: preference writes and the badge-count poll.
 *
 * The changelog moved to ChangelogController when it grew a filtered page, an
 * image route and an announcement to acknowledge.
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
}
