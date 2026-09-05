<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Analytics\Actions\GetDashboardMetrics;
use App\Domain\Analytics\DTOs\DateRange;
use App\Domain\Posts\Models\PostDraft;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The page. Renders with the first payload already in it, so the dashboard
     * arrives populated rather than as six skeletons that resolve a beat later.
     */
    public function __invoke(Request $request, GetDashboardMetrics $metrics): Response
    {
        $draft = PostDraft::query()->where('user_id', $request->user()->id)->first();

        return inertia('Dashboard', [
            'firstName' => $this->firstName($request),
            'metrics' => $this->resolve($request, $metrics),
            // An interrupted add-post wizard, surfaced where the advertiser
            // will actually come back to. A draft nobody is told about is a
            // draft nobody resumes.
            'postDraft' => $draft === null ? null : [
                'step' => $draft->step,
                'savedAt' => $draft->updated_at?->toIso8601String(),
                'summary' => $this->draftSummary($draft),
            ],
        ]);
    }

    /**
     * The same payload as JSON, for range and granularity changes.
     *
     * One endpoint for every widget: the page is useless half-loaded, and six
     * separate calls would let the stat cards and the chart disagree about
     * which range they are describing.
     */
    public function metrics(Request $request, GetDashboardMetrics $metrics): JsonResponse
    {
        return response()->json($this->resolve($request, $metrics));
    }

    /**
     * One line naming what the draft is, from whatever it has so far.
     *
     * A card that says only "you have an unfinished post" is a card people
     * ignore, because it does not say whether it is worth the click.
     */
    private function draftSummary(PostDraft $draft): string
    {
        $payload = $draft->payload;
        $domain = is_string($payload['website_domain'] ?? null) ? $payload['website_domain'] : null;
        $project = is_string($payload['project_name'] ?? null) ? $payload['project_name'] : null;

        return match (true) {
            $domain !== null && $project !== null => "{$domain} for {$project}",
            $domain !== null => $domain,
            $project !== null => "A post for {$project}",
            default => 'An unfinished post',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function resolve(Request $request, GetDashboardMetrics $metrics): array
    {
        $range = DateRange::fromRequest($request);

        // The sidebar's project scope, if one is chosen, filters the dashboard
        // as well — the buying context follows the advertiser everywhere.
        $projectId = $request->integer('project') ?: null;

        return $metrics->handle($request->user(), $range, $range->granularityFrom($request), $projectId);
    }

    private function firstName(Request $request): string
    {
        $name = trim((string) $request->user()->name);
        $first = explode(' ', $name)[0] ?? '';

        return $first === '' ? 'there' : $first;
    }
}
