<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

/**
 * The six tabs of a project's page.
 *
 * Validated server-side rather than trusted from the query string, so
 * `?tab=<anything>` lands on General instead of rendering an empty panel or
 * echoing the value back into the page.
 */
enum ProjectTab: string
{
    case General = 'general';
    case Posts = 'posts';
    case Settings = 'settings';
    case Statistics = 'statistics';
    case History = 'history';
    case Competitors = 'competitors';

    public static function tryFromRequest(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::General) : self::General;
    }

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Posts => 'Post management',
            self::Settings => 'Project settings',
            self::Statistics => 'Statistics',
            self::History => 'History',
            self::Competitors => 'Competitors',
        };
    }
}
