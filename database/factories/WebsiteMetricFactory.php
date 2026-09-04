<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Enums\MetricSource;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteMetric>
 */
class WebsiteMetricFactory extends Factory
{
    protected $model = WebsiteMetric::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'monthly_traffic' => $this->faker->numberBetween(500, 800_000),
            'ahrefs_dr' => $this->faker->numberBetween(1, 92),
            'moz_da' => $this->faker->numberBetween(1, 90),
            'semrush_as' => $this->faker->numberBetween(1, 80),
            'spam_score' => $this->faker->numberBetween(0, 45),
            'referring_domains' => $this->faker->numberBetween(20, 40_000),
            'organic_keywords' => $this->faker->numberBetween(50, 90_000),
            'traffic_value_cents' => $this->faker->numberBetween(50_000, 900_000_00),
            'indexed_pages' => $this->faker->numberBetween(50, 400_000),
            'traffic_by_country' => null,
            'source' => MetricSource::Ahrefs,
            'fetched_at' => now(),
        ];
    }
}
