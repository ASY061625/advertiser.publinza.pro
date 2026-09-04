<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteSamplePost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebsiteSamplePost>
 */
class WebsiteSamplePostFactory extends Factory
{
    protected $model = WebsiteSamplePost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->sentence(6);

        return [
            'website_id' => Website::factory(),
            'title' => rtrim($title, '.'),
            'url' => 'https://example.test/'.Str::slug($title),
            'published_at' => $this->faker->dateTimeBetween('-14 months', '-1 month'),
            'sort_order' => 0,
        ];
    }
}
