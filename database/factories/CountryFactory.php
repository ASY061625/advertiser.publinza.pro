<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->countryCode(),
            'name' => fake()->country(),
            'region' => fake()->randomElement(['Europe', 'North America', 'Asia', 'South America', 'Oceania']),
        ];
    }
}
