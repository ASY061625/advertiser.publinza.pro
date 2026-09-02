<?php

declare(strict_types=1);

namespace App\Domain\Projects\Policies;

use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Models\User;

/**
 * A project belongs to exactly one advertiser and is theirs to manage.
 *
 * `status` is cast to ProjectStatus, so it is compared against the enum. The
 * previous version compared it against the string 'draft' — a value the enum
 * has never had — which under a strict comparison made every one of these
 * return false and denied every edit.
 */
class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }

    /** Archived projects are read-only until they are restored. */
    public function update(User $user, Project $project): bool
    {
        return $this->view($user, $project) && $project->status === ProjectStatus::Active;
    }

    public function archive(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function restore(User $user, Project $project): bool
    {
        return $this->view($user, $project) && $project->status === ProjectStatus::Archived;
    }

    /**
     * Deleting is allowed for any project the advertiser owns; whether it is
     * *safe* depends on the posts on it, which DeleteProject decides. Splitting
     * it that way keeps the reason for a refusal explainable — a policy can
     * only say no, an action can say why.
     */
    public function delete(User $user, Project $project): bool
    {
        return $this->view($user, $project);
    }
}
