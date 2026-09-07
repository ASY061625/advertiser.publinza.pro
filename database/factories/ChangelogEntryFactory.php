<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\System\Enums\ChangelogType;
use App\Domain\System\Models\ChangelogEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChangelogEntry>
 */
class ChangelogEntryFactory extends Factory
{
    protected $model = ChangelogEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim($this->faker->sentence(5), '.');

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1, 999_999),
            'body' => $this->faker->paragraphs(2, true),
            'image_path' => null,
            'type' => $this->faker->randomElement(ChangelogType::cases())->value,
            'is_major' => false,
            'published_at' => now()->subDays($this->faker->numberBetween(0, 120)),
        ];
    }

    public function major(): self
    {
        return $this->state(fn (): array => ['is_major' => true]);
    }

    public function unpublished(): self
    {
        return $this->state(fn (): array => ['published_at' => null]);
    }

    public function type(ChangelogType $type): self
    {
        return $this->state(fn (): array => ['type' => $type->value]);
    }
}
