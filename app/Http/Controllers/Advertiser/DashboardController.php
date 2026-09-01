<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Analytics\Actions\GetDashboardMetrics;
use App\Domain\Analytics\DTOs\DateRange;
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
        return inertia('Dashboard', [
            'firstName' => $this->firstName($request),
            'metrics' => $this->resolve($request, $metrics),
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
