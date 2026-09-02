<?php

declare(strict_types=1);

namespace App\Domain\Projects\DTOs;

use App\Domain\Projects\Support\UrlNormalizer;
use App\Support\HtmlSanitizer;
use App\Support\PublicSuffix;

/**
 * Everything the three-step wizard collects, normalised.
 *
 * Normalisation happens here rather than in the controller so the same rules
 * apply whether a payload arrives from the final submit or from an autosave —
 * a draft that stored a differently-shaped URL from the one the submit stores
 * would resume into a form that disagreed with itself.
 */
final readonly class ProjectWizardData
{
    /** The palette the swatch picker offers. Anything else is refused. */
    public const COLORS = ['#1d4ed8', '#0f9d74', '#7e22ce', '#d97706', '#dc2626', '#0891b2', '#4d7c0f', '#be185d'];

    public const MAX_TASK_CHARS = 3000;

    /**
     * @param  list<int>  $sensitiveTopicIds
     * @param  list<int>  $countryIds
     * @param  list<int>  $languageIds
     * @param  list<array{anchor_text: string, url: string}>  $landingPages
     */
    public function __construct(
        public string $websiteUrl,
        public string $name,
        public ?int $categoryId,
        public string $color,
        public array $sensitiveTopicIds,
        public array $countryIds,
        public array $languageIds,
        public ?string $publisherTask,
        public array $landingPages,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $websiteUrl = UrlNormalizer::normalize((string) ($input['website_url'] ?? '')) ?? '';

        return new self(
            websiteUrl: $websiteUrl,
            name: mb_substr(trim((string) ($input['name'] ?? '')), 0, 60),
            categoryId: self::id($input['category_id'] ?? null),
            color: self::color($input['color'] ?? null, $websiteUrl),
            sensitiveTopicIds: self::ids($input['sensitive_topic_ids'] ?? []),
            countryIds: self::ids($input['country_ids'] ?? []),
            languageIds: self::ids($input['language_ids'] ?? []),
            // The brief is authored in a contenteditable, so it arrives as
            // HTML the browser produced — which means it arrives as HTML a
            // determined user produced. Sanitised on the way in, once.
            publisherTask: self::task($input['publisher_task'] ?? null),
            landingPages: self::landingPages($input['landing_pages'] ?? [], $websiteUrl),
        );
    }

    /**
     * A deterministic colour from the domain, so the same site always suggests
     * the same swatch and two projects for different sites rarely collide.
     */
    public static function defaultColorFor(string $websiteUrl): string
    {
        $host = UrlNormalizer::hostOf($websiteUrl) ?? $websiteUrl;
        $registrable = PublicSuffix::registrable($host) ?? $host;

        $index = hexdec(substr(sha1($registrable), 0, 8)) % count(self::COLORS);

        return self::COLORS[$index];
    }

    /**
     * Which landing pages are not on the promoted site.
     *
     * Returned as row index => reason so the wizard can put each message under
     * the row it belongs to instead of showing one summary error.
     *
     * @return array<int, string>
     */
    public function offSiteLandingPages(): array
    {
        $promotedHost = UrlNormalizer::hostOf($this->websiteUrl);

        if ($promotedHost === null) {
            return [];
        }

        $errors = [];

        foreach ($this->landingPages as $index => $row) {
            $host = UrlNormalizer::hostOf($row['url']);

            if ($host === null) {
                $errors[$index] = 'That does not look like a web address.';

                continue;
            }

            if (! PublicSuffix::sameSite($host, $promotedHost)) {
                $errors[$index] = sprintf(
                    'This has to be a page on %s — you entered %s. Landing pages are the pages on your own site that the links point to.',
                    PublicSuffix::registrable($promotedHost) ?? $promotedHost,
                    $host,
                );
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function toDraftPayload(array $input): array
    {
        return [
            'website_url' => $this->websiteUrl,
            'name' => $this->name,
            'category_id' => $this->categoryId,
            'color' => $this->color,
            'sensitive_topic_ids' => $this->sensitiveTopicIds,
            'country_ids' => $this->countryIds,
            'language_ids' => $this->languageIds,
            'publisher_task' => $this->publisherTask,
            'landing_pages' => $this->landingPages,
            // Carried through untouched: it is a display aid the client fetched
            // and nothing server-side reads it back.
            'preview' => is_array($input['preview'] ?? null) ? $input['preview'] : null,
        ];
    }

    private static function color(mixed $value, string $websiteUrl): string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($value, self::COLORS, true) ? $value : self::defaultColorFor($websiteUrl);
    }

    private static function task(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = HtmlSanitizer::clean($value);

        // Measured on the text, not the markup: a counter that charged people
        // for the <strong> tags they cannot see would be nonsense.
        if (mb_strlen(strip_tags($clean)) > self::MAX_TASK_CHARS) {
            $clean = mb_substr($clean, 0, self::MAX_TASK_CHARS * 4);
        }

        return trim(strip_tags($clean)) === '' ? null : $clean;
    }

    private static function id(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * @return list<int>
     */
    private static function ids(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = array_map(static fn (mixed $v): int => is_numeric($v) ? (int) $v : 0, $value);

        return array_values(array_unique(array_filter($ids, static fn (int $v): bool => $v > 0)));
    }

    /**
     * @return list<array{anchor_text: string, url: string}>
     */
    private static function landingPages(mixed $value, string $websiteUrl): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];

        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $anchor = mb_substr(trim((string) ($row['anchor_text'] ?? '')), 0, 120);
            $url = UrlNormalizer::normalize((string) ($row['url'] ?? ''));

            // A row that is blank on both sides is someone who added a row and
            // changed their mind, not an error to report back at them.
            if ($anchor === '' && $url === null) {
                continue;
            }

            $rows[] = ['anchor_text' => $anchor, 'url' => $url ?? (string) ($row['url'] ?? '')];
        }

        return $rows;
    }
}
