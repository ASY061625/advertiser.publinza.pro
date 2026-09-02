<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Copies a post's brief into another project as a fresh draft.
 *
 * A draft, deliberately, and never a copy of the original's status: duplicating
 * a posted placement must not produce a second placement that claims to be
 * live, and it must not move money. The copy carries the brief — website,
 * anchor, target, content mode, price — and nothing about what happened to the
 * original: no order, no article, no published URL, no history.
 */
final class DuplicatePost
{
    public function handle(User $user, Post $post, int $projectId, ?int $folderId = null): Post
    {
        if ($post->user_id !== $user->id) {
            throw new RuntimeException('That post is not yours to duplicate.');
        }

        $project = Project::query()->where('user_id', $user->id)->find($projectId);

        if ($project === null) {
            throw new RuntimeException('That project does not exist on your account.');
        }

        // A folder is only valid inside its own project.
        $folder = $folderId === null ? null : $project->folders()->find($folderId);

        return DB::transaction(fn (): Post => Post::query()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'folder_id' => $folder?->id,
            'website_id' => $post->website_id,
            'status' => PostStatus::Draft,
            'anchor_text' => $post->anchor_text,
            'target_url' => $post->target_url,
            'content_mode' => $post->content_mode,
            // Re-read from the current post rather than the catalog: the copy
            // should quote what this advertiser last paid, and checkout prices
            // it again anyway.
            'price_cents' => $post->price_cents,
        ]));
    }
}
