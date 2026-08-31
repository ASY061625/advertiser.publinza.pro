<?php

declare(strict_types=1);

namespace App\Domain\Projects\Policies;

use App\Domain\Projects\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }

    public function update(User $user, Project $project): bool
    {
        return $project->user_id === $user->id && $project->status === 'draft';
    }

    public function publish(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }
}
