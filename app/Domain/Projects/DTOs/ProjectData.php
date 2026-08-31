<?php

declare(strict_types=1);

namespace App\Domain\Projects\DTOs;

final readonly class ProjectData
{
    public function __construct(
        public string $name,
        public string $targetUrl,
        public string $anchorText,
        public ?string $brief = null,
    ) {}

    /**
     * @param  array{name: string, target_url: string, anchor_text: string, brief?: string|null}  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            name: $attributes['name'],
            targetUrl: $attributes['target_url'],
            anchorText: $attributes['anchor_text'],
            brief: $attributes['brief'] ?? null,
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'target_url' => $this->targetUrl,
            'anchor_text' => $this->anchorText,
            'brief' => $this->brief,
        ];
    }
}
