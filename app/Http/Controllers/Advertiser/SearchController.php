<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Search\Support\GlobalSearch;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The global palette's one endpoint.
 *
 * Websites, projects, posts and conversations in a single response, because the
 * palette renders all four groups from every keystroke and four requests inside
 * a 200ms debounce is four times the chance of one of them landing late and
 * painting a stale group.
 *
 * The Actions group is not here. It is a fixed list of eight things this app can
 * do, matched against the query in the browser — asking a server whether "Log
 * out" contains the letters you typed is a round trip to learn something the
 * client already knows.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, GlobalSearch $search): JsonResponse
    {
        $term = $request->string('q')->trim()->limit(60, '')->value();
        $user = $request->user();

        /*
         * An empty box is not an empty answer.
         *
         * Opening the palette with nothing typed shows what this person looked
         * at recently, so Cmd+K is a way back to yesterday's work and not only
         * a search box.
         */
        if (mb_strlen($term) < GlobalSearch::MIN_TERM) {
            return response()->json([
                'query' => $term,
                'groups' => $search->recent($user),
                'recent' => true,
            ]);
        }

        return response()->json([
            'query' => $term,
            'groups' => $search->search($user, $term),
            'recent' => false,
        ]);
    }
}
