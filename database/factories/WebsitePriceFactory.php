<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsitePrice;
use App\Domain\Trading\Enums\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsitePrice>
 */
class WebsitePriceFactory extends Factory
{
    protected $model = WebsitePrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'service_type' => ServiceType::ArticlePlacement,
            'price_cents' => $this->faker->numberBetween(4_000, 90_000),
            // Not every publisher writes the article; zero means they do not,
            // which is what the "+$45 writing" line under the price reads.
            'writing_fee_cents' => $this->faker->boolean(65) ? $this->faker->numberBetween(2_000, 12_000) : 0,
            'express_fee_cents' => 0,
        ];
    }
}
