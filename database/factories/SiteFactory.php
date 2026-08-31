<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain' => fake()->unique()->domainName(),
            'language' => fake()->randomElement(['en', 'de', 'fr', 'es']),
            'category' => fake()->randomElement(['Technology', 'Finance', 'Health', 'Travel', 'Marketing']),
            'price_minor_units' => fake()->numberBetween(5_000, 120_000),
            // A wide traffic spread is what makes the quant-bars readable.
            'traffic' => fake()->numberBetween(500, 900_000),
            'domain_rating' => fake()->numberBetween(5, 90),
            'domain_authority' => fake()->numberBetween(5, 90),
            'spam_score' => fake()->numberBetween(0, 40),
            'status' => 'approved',
            'approved_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => 'pending', 'approved_at' => null]);
    }
}
