<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\SensitiveTopic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SensitiveTopic>
 */
class SensitiveTopicFactory extends Factory
{
    protected $model = SensitiveTopic::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement([
            'Gambling', 'Cryptocurrency', 'Adult', 'CBD', 'Pharmacy', 'Forex', 'Vaping', 'Politics',
        ]).' '.$this->faker->unique()->numberBetween(1, 9999);

        return ['name' => $name, 'slug' => Str::slug($name), 'description' => null];
    }
}
