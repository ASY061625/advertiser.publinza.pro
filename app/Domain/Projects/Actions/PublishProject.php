<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Models\Project;
use RuntimeException;

final class PublishProject
{
    public function handle(Project $project): Project
    {
        if ($project->status !== 'draft') {
            throw new RuntimeException('Only a draft project can be published.');
        }

        $project->update(['status' => 'new']);

        return $project->refresh();
    }
}
