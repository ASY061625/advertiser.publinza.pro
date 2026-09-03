<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\LandingPage;
use App\Domain\Projects\Models\ProjectFolder;
use RuntimeException;

/**
 * Deletes a folder once nothing depends on it.
 *
 * A folder is not just a label: it carries the writer instructions the posts
 * inside it were ordered under. Deleting one out from under a post in progress
 * would leave a publisher writing against a brief that no longer exists, so a
 * folder with live posts is refused — and the refusal names the count, because
 * "cannot delete" without a reason is a dead end.
 *
 * Finished posts do not block it. They keep a copy of what they were, and the
 * folder column is nulled rather than cascading, so history stays readable.
 *
 * The last folder is refused too. Every landing page lives in one, so a project
 * with no folders has nowhere to put the next page.
 */
final class DeleteFolder
{
    public function handle(ProjectFolder $folder): void
    {
        $siblings = ProjectFolder::query()->where('project_id', $folder->project_id)->count();

        if ($siblings <= 1) {
            throw new RuntimeException(
                'This is the project’s only folder, and every landing page has to live in one. '
                .'Add another folder first, then delete this one.'
            );
        }

        $blocking = Post::query()
            ->where('folder_id', $folder->id)
            ->whereNotIn('status', self::terminalValues())
            ->count();

        if ($blocking > 0) {
            throw new RuntimeException(sprintf(
                '%d post%s ordered under “%s” %s still in progress, and %s written against this folder’s brief. '
                .'Wait for %s to finish or cancel %s, then delete the folder.',
                $blocking,
                $blocking === 1 ? '' : 's',
                $folder->name,
                $blocking === 1 ? 'is' : 'are',
                $blocking === 1 ? 'it is being' : 'they are being',
                $blocking === 1 ? 'it' : 'them',
                $blocking === 1 ? 'it' : 'them',
            ));
        }

        $pages = LandingPage::query()->where('folder_id', $folder->id)->count();

        if ($pages > 0) {
            throw new RuntimeException(sprintf(
                '“%s” still holds %d landing page%s. Move %s to another folder first — deleting the folder '
                .'would leave %s with no brief to be written against.',
                $folder->name,
                $pages,
                $pages === 1 ? '' : 's',
                $pages === 1 ? 'it' : 'them',
                $pages === 1 ? 'it' : 'them',
            ));
        }

        $folder->delete();
    }

    /**
     * @return list<string>
     */
    private static function terminalValues(): array
    {
        return array_values(array_map(
            static fn (PostStatus $status): string => $status->value,
            array_filter(PostStatus::cases(), static fn (PostStatus $s): bool => $s->isTerminal()),
        ));
    }
}
