<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Deletes a project, but only once nothing is still in flight.
 *
 * A project with live work has money committed against it and publishers
 * writing for it. Soft-deleting the row would hide the project while its posts
 * carried on spending, and the advertiser would have no screen left to watch
 * them from. So the refusal is a real one, and it names the posts in the way.
 */
final class DeleteProject
{
    /** How long a deleted project stays recoverable. */
    public const RETENTION_DAYS = 30;

    public function handle(Project $project): void
    {
        $blocking = $this->blockingPosts($project);

        if ($blocking->isNotEmpty()) {
            $count = $blocking->count();

            throw new RuntimeException(sprintf(
                '%d post%s on this project %s not finished. A post counts until it has been placed, rejected or '
                .'refunded — wait for %s to settle, or cancel what is still being written, then delete the project.',
                $count,
                $count === 1 ? '' : 's',
                $count === 1 ? 'has' : 'have',
                $count === 1 ? 'it' : 'them',
            ));
        }

        // Soft delete, kept for RETENTION_DAYS: the posts, their history and
        // the money they moved stay readable, because an invoice referencing a
        // placement has to keep resolving after the project it belonged to is
        // gone — and a deletion someone regrets on the same afternoon should
        // not be the end of it.
        $project->delete();
    }

    /**
     * The posts standing in the way, named so the screen can link to them.
     *
     * The Danger zone lists these before anything is typed rather than letting
     * someone type the project's name and then refusing — and the refusal in
     * handle() reads the same query, so the list and the rule cannot drift.
     *
     * @return Collection<int, Post>
     */
    public function blockingPosts(Project $project, int $limit = 50): Collection
    {
        return $project->posts()
            ->whereNotIn('status', $this->terminalValues())
            ->with('website:id,domain')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'website_id', 'status', 'anchor_text']);
    }

    /**
     * @return list<string>
     */
    private function terminalValues(): array
    {
        return array_values(array_map(
            static fn (PostStatus $status): string => $status->value,
            array_filter(PostStatus::cases(), static fn (PostStatus $s): bool => $s->isTerminal()),
        ));
    }
}
