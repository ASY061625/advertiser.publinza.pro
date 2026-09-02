<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Projects\Models\Project;
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
    public function handle(Project $project): void
    {
        $blocking = $project->posts()
            ->whereNotIn('status', $this->terminalValues())
            ->count();

        if ($blocking > 0) {
            throw new RuntimeException(sprintf(
                '%d post%s on this project %s still in progress. Cancel or wait for %s to finish, then delete the project.',
                $blocking,
                $blocking === 1 ? '' : 's',
                $blocking === 1 ? 'is' : 'are',
                $blocking === 1 ? 'it' : 'them',
            ));
        }

        // Soft delete: the posts, their history and the money they moved stay
        // readable, because an invoice referencing a placement has to keep
        // resolving after the project it belonged to is gone.
        $project->delete();
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
