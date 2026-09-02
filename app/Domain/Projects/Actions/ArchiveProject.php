<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;

/**
 * Archiving is reversible and touches nothing but the project's own status.
 *
 * In-flight posts keep running: archiving says "I have finished adding to
 * this", not "abandon the work I have already paid for". Cancelling those
 * would move money, which is a different decision with a different button.
 */
final class ArchiveProject
{
    public function handle(Project $project): Project
    {
        $project->update(['status' => ProjectStatus::Archived]);

        return $project;
    }

    public function restore(Project $project): Project
    {
        $project->update(['status' => ProjectStatus::Active]);

        return $project;
    }
}
