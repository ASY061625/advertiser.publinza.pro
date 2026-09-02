<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\DTOs\ProjectData;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Models\User;

final class CreateProject
{
    public function handle(User $user, ProjectData $data): Project
    {
        return Project::query()->create([
            ...$data->toAttributes(),
            'user_id' => $user->id,
            // Active on creation. ProjectStatus has two cases, Active and
            // Archived; writing 'draft' here produced a row that threw a
            // ValueError the moment anything read it back.
            'status' => ProjectStatus::Active,
        ]);
    }
}
