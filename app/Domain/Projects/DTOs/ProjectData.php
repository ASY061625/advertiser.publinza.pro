<?php

declare(strict_types=1);

namespace App\Domain\Projects\DTOs;

/**
 * A project's editable fields.
 *
 * These are the columns `projects` actually has. The previous version carried
 * `target_url`, `anchor_text` and `brief` — fields belonging to a *post* — so
 * every create tried to insert columns that do not exist on this table and
 * omitted `website_url`, which is NOT NULL.
 */
final readonly class ProjectData
{
    public function __construct(
        public string $name,
        public string $websiteUrl,
        public ?int $categoryId = null,
        public ?string $publisherTask = null,
    ) {}

    /**
     * @param  array{name: string, website_url: string, category_id?: int|string|null, publisher_task?: string|null}  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $categoryId = $attributes['category_id'] ?? null;

        return new self(
            name: trim($attributes['name']),
            websiteUrl: trim($attributes['website_url']),
            categoryId: $categoryId === null || $categoryId === '' ? null : (int) $categoryId,
            publisherTask: $attributes['publisher_task'] ?? null,
        );
    }

    /**
     * @return array<string, string|int|null>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'website_url' => $this->websiteUrl,
            'category_id' => $this->categoryId,
            'publisher_task' => $this->publisherTask,
        ];
    }
}
