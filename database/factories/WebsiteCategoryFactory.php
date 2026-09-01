<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\WebsiteCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebsiteCategory>
 */
class WebsiteCategoryFactory extends Factory
{
    protected $model = WebsiteCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return ['name' => ucfirst($name), 'slug' => Str::slug($name), 'sort_order' => 0];
    }
}
