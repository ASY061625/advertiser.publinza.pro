<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->catchPhrase(),
            'target_url' => fake()->url(),
            'anchor_text' => fake()->words(3, true),
            'brief' => fake()->paragraph(),
            'status' => 'draft',
        ];
    }
}
