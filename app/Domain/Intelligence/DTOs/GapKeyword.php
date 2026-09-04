<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\DTOs;

/**
 * A keyword a competitor ranks for and this project does not.
 *
 * `url` is the competitor's ranking page, which is the useful half: knowing
 * they rank for "invoice software for freelancers" is a fact, and seeing the
 * page they rank with is a brief.
 */
final readonly class GapKeyword
{
    public function __construct(
        public string $keyword,
        public int $position,
        public int $volume,
        public int $difficulty,
        public ?string $url = null,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            keyword: (string) ($row['keyword'] ?? ''),
            position: (int) ($row['position'] ?? 0),
            volume: (int) ($row['volume'] ?? 0),
            difficulty: (int) ($row['difficulty'] ?? 0),
            url: isset($row['url']) && is_string($row['url']) ? $row['url'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'keyword' => $this->keyword,
            'position' => $this->position,
            'volume' => $this->volume,
            'difficulty' => $this->difficulty,
            'url' => $this->url,
        ];
    }
}
