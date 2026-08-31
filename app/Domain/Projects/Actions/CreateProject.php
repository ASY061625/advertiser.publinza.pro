<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\DTOs\ProjectData;
use App\Domain\Projects\Models\Project;
use App\Models\User;

final class CreateProject
{
    public function handle(User $user, ProjectData $data): Project
    {
        return Project::query()->create([
            ...$data->toAttributes(),
            'user_id' => $user->id,
            'status' => 'draft',
        ]);
    }
}
