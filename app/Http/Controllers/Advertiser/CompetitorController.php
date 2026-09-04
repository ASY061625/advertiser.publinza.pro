<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Intelligence\Actions\AddCompetitor;
use App\Domain\Intelligence\Actions\RefreshCompetitor;
use App\Domain\Intelligence\Models\Competitor;
use App\Domain\Projects\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The competitors of one project.
 *
 * Every action authorises the *project* and then confirms the competitor
 * belongs to it. Both halves matter: the first stops another advertiser's
 * project being read, and the second stops a competitor id from one project
 * being refreshed or deleted through another project's URL.
 */
class CompetitorController extends Controller
{
    public function store(Request $request, Project $project, AddCompetitor $add): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            // Deliberately loose: what counts as a domain is decided by
            // UrlNormalizer inside the action, which is the same judge the
            // project's own website URL is put in front of. A second, stricter
            // rule here would refuse addresses the project itself is stored at.
            'domain' => ['required', 'string', 'max:253'],
            'label' => ['nullable', 'string', 'max:120'],
        ]);

        $add->handle($project, $validated['domain'], $validated['label'] ?? null);

        return back()->with('success', 'Competitor added. Its metrics are on their way.');
    }

    public function update(Request $request, Project $project, Competitor $competitor): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->assertBelongs($project, $competitor);

        $validated = $request->validate(['label' => ['nullable', 'string', 'max:120']]);
        $label = trim((string) ($validated['label'] ?? ''));

        $competitor->forceFill(['label' => $label === '' ? null : $label])->save();

        return back()->with('success', 'Label saved.');
    }

    public function destroy(Project $project, Competitor $competitor): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->assertBelongs($project, $competitor);

        $competitor->delete();

        return back()->with('success', 'Competitor removed.');
    }

    public function refresh(Project $project, Competitor $competitor, RefreshCompetitor $refresh): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->assertBelongs($project, $competitor);

        $refresh->handle($competitor);

        return back()->with('success', 'Refreshing — the new figures will appear here shortly.');
    }

    /**
     * The gap drawer: keywords they rank for and this project does not.
     *
     * JSON rather than an Inertia prop. The list is a hundred rows per
     * competitor and up to ten competitors, and shipping all of it with every
     * render of the tab would be a megabyte nobody opened.
     */
    public function gapKeywords(Project $project, Competitor $competitor): JsonResponse
    {
        $this->authorize('view', $project);
        $this->assertBelongs($project, $competitor);

        $metric = $competitor->latestMetric;

        return response()->json([
            'domain' => $competitor->domain,
            'label' => $competitor->label,
            'updatedAt' => $metric?->fetched_at?->toIso8601String(),
            'keywords' => array_values(array_filter(
                (array) ($metric?->gap_keywords ?? []),
                'is_array',
            )),
        ]);
    }

    /**
     * A competitor id is only meaningful inside its own project.
     *
     * Route model binding resolves it from the whole table, so without this a
     * competitor belonging to project A could be refreshed — spending a metered
     * vendor call — or deleted through project B's URL by anyone who owns B.
     */
    private function assertBelongs(Project $project, Competitor $competitor): void
    {
        abort_unless($competitor->project_id === $project->id, 404);
    }
}
