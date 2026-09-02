<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Posts\DTOs\PostFilters;
use App\Domain\Posts\Models\SavedView;
use App\Http\Controllers\Controller;
use App\Support\PostGridPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The bits of /posts that persist between visits: saved filter presets and the
 * column arrangement. Separate from PostController because none of it is about
 * posts — it is about how one advertiser likes to look at them.
 */
class PostGridController extends Controller
{
    public function storeView(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:80'],
        ]);

        $user = $request->user();
        $name = trim((string) $validated['name']);

        // The view stores the filters as the URL carries them, so restoring one
        // is the same operation as opening a shared link.
        $query = PostFilters::fromRequest($request)->toQuery();

        if ($query === []) {
            return back()->with('error', 'Set at least one filter before saving a view.');
        }

        // updateOrCreate against the unique key: saving over a name you already
        // used replaces that view, which is what "save" means to everyone.
        SavedView::query()->updateOrCreate(
            ['user_id' => $user->id, 'surface' => PostGridPreferences::SURFACE, 'name' => $name],
            ['query' => $query],
        );

        return back()->with('success', "Saved “{$name}”.");
    }

    public function destroyView(Request $request, SavedView $view): RedirectResponse
    {
        abort_unless($view->user_id === $request->user()->id, 404);

        $view->delete();

        return back()->with('success', "Deleted “{$view->name}”.");
    }

    /**
     * Column order and visibility. Fire-and-forget from the client: the UI has
     * already moved, and a failed write only means the next browser starts from
     * the defaults.
     */
    public function storeColumns(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['array'],
            'order.*' => ['string'],
            'hidden' => ['array'],
            'hidden.*' => ['string'],
        ]);

        PostGridPreferences::store(
            $request->user(),
            array_map('strval', $validated['order'] ?? []),
            array_map('strval', $validated['hidden'] ?? []),
        );

        return response()->json(['ok' => true]);
    }
}
