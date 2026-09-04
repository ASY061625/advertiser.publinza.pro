<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Intelligence\Enums\FetchState;
use App\Domain\Intelligence\Models\Competitor;
use App\Domain\Projects\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Competitor>
 */
class CompetitorFactory extends Factory
{
    protected $model = Competitor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'is_self' => false,
            'domain' => $this->faker->unique()->domainName(),
            'label' => null,
            'added_at' => now(),
            'fetch_state' => FetchState::Ready,
        ];
    }

    /** The project's own site rather than a rival. */
    public function self(): static
    {
        return $this->state(fn (): array => ['is_self' => true, 'label' => null]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['fetch_state' => FetchState::Pending]);
    }

    public function failed(string $reason = 'The API answered 503'): static
    {
        return $this->state(fn (): array => ['fetch_state' => FetchState::Failed, 'fetch_error' => $reason]);
    }
}
