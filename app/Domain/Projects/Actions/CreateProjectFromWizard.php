<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\DTOs\ProjectWizardData;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\LandingPage;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectDraft;
use App\Domain\Projects\Models\ProjectFolder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Turns a finished wizard into a project.
 *
 * One transaction: a project without its landing pages, or with targeting
 * attached but no default folder, is a half-made thing the advertiser would
 * have to repair by hand. Either all of it exists or none of it does — and the
 * draft is only discarded once the project is real.
 */
final class CreateProjectFromWizard
{
    public function handle(User $user, ProjectWizardData $data): Project
    {
        return DB::transaction(function () use ($user, $data): Project {
            $project = Project::query()->create([
                'user_id' => $user->id,
                'name' => $data->name,
                'website_url' => $data->websiteUrl,
                'category_id' => $data->categoryId,
                'color' => $data->color,
                'status' => ProjectStatus::Active,
                'publisher_task' => $data->publisherTask,
            ]);

            // sync() rather than attach(): the ids have been filtered to the
            // ones that exist, and sync is idempotent if this ever runs twice.
            $project->sensitiveTopics()->sync($data->sensitiveTopicIds);
            $project->countries()->sync($data->countryIds);
            $project->languages()->sync($data->languageIds);

            // Every project gets one folder so posts always have somewhere to
            // live; the wizard does not ask for it because there is no useful
            // answer at this point other than "General".
            $folder = ProjectFolder::query()->create([
                'project_id' => $project->id,
                'name' => 'General',
                'sort_order' => 0,
            ]);

            foreach ($data->landingPages as $index => $row) {
                LandingPage::query()->create([
                    'project_id' => $project->id,
                    'folder_id' => $folder->id,
                    'anchor_text' => $row['anchor_text'],
                    'url' => $row['url'],
                    'sort_order' => $index,
                ]);
            }

            ProjectDraft::query()->where('user_id', $user->id)->delete();

            return $project;
        });
    }
}
