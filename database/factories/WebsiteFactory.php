<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Enums\LinkType;
use App\Domain\Catalog\Models\Country;
use App\Domain\Catalog\Models\Language;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Website>
 */
class WebsiteFactory extends Factory
{
    protected $model = Website::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return [
            'domain' => $domain,
            'slug' => Str::slug($domain),
            'title' => fake()->catchPhrase(),
            'description' => fake()->sentence(14),
            'category_id' => WebsiteCategory::factory(),
            'primary_language_id' => Language::factory(),
            'country_id' => Country::factory(),
            'is_active' => true,
            'is_featured' => fake()->boolean(12),
            'accepts_sensitive_topics' => [],
            'publication_period_hours' => fake()->randomElement([24, 48, 72, 120, 168]),
            'link_type' => fake()->boolean(80) ? LinkType::Dofollow : LinkType::Nofollow,
            'links_allowed' => fake()->numberBetween(1, 3),
            'max_links' => fake()->numberBetween(1, 4),
            'min_words' => fake()->randomElement([500, 700, 800, 1000, 1200]),
            'sample_url' => "https://{$domain}/example-post",
            'guidelines' => fake()->paragraph(),
            'marks_sponsored' => fake()->boolean(35),
            'link_guarantee_months' => fake()->randomElement([0, 6, 12, 24]),
            'accepts_images' => fake()->boolean(85),
            'accepts_embeds' => fake()->boolean(45),
            'domain_registered_at' => fake()->dateTimeBetween('-18 years', '-1 year'),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }
}
