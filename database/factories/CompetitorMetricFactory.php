<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Intelligence\Models\Competitor;
use App\Domain\Intelligence\Models\CompetitorMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetitorMetric>
 */
class CompetitorMetricFactory extends Factory
{
    protected $model = CompetitorMetric::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competitor_id' => Competitor::factory(),
            'organic_traffic' => $this->faker->numberBetween(1_000, 500_000),
            'organic_keywords' => $this->faker->numberBetween(100, 50_000),
            'dr' => $this->faker->numberBetween(1, 90),
            'da' => $this->faker->numberBetween(1, 90),
            'referring_domains' => $this->faker->numberBetween(10, 20_000),
            'backlinks' => $this->faker->numberBetween(50, 900_000),
            'traffic_value_cents' => $this->faker->numberBetween(10_000, 90_000_000),
            'provider' => 'sample',
            'traffic_history' => collect(range(11, 0))
                ->map(fn (int $back): array => [
                    'month' => now()->subMonths($back)->format('Y-m'),
                    'traffic' => $this->faker->numberBetween(1_000, 500_000),
                ])
                ->all(),
            'shared_keywords' => $this->faker->numberBetween(10, 500),
            'gap_keywords' => [],
            'link_categories' => [],
            'fetched_at' => now(),
        ];
    }
}
