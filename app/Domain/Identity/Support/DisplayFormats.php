<?php

declare(strict_types=1);

namespace App\Domain\Identity\Support;

/**
 * The date and number formats somebody can choose, and what each looks like.
 *
 * A closed set of named patterns rather than free ICU skeletons. The set is
 * small enough to show an example of each, which is the only way anybody picks
 * one correctly — "d MMM y" means nothing next to "6 Sep 2026" — and it means a
 * stored preference can never be a pattern that breaks every date on the site.
 *
 * The names travel to the browser and are resolved there against `Intl`, so the
 * server and the client agree on what "medium" means without shipping a
 * formatting library to either.
 */
final class DisplayFormats
{
    /** @var array<string, array{label: string, example: string}> */
    private const DATES = [
        'medium' => ['label' => 'Sep 6, 2026', 'example' => 'Sep 6, 2026'],
        'euro' => ['label' => '6 September 2026', 'example' => '6 September 2026'],
        'iso' => ['label' => '2026-09-06', 'example' => '2026-09-06'],
        'slash_dmy' => ['label' => '06/09/2026', 'example' => '06/09/2026'],
        'slash_mdy' => ['label' => '09/06/2026', 'example' => '09/06/2026'],
    ];

    /** @var array<string, array{label: string, example: string}> */
    private const NUMBERS = [
        'plain' => ['label' => '1,234,567.89', 'example' => '1,234,567.89'],
        'space' => ['label' => '1 234 567,89', 'example' => '1 234 567,89'],
        'euro' => ['label' => '1.234.567,89', 'example' => '1.234.567,89'],
    ];

    public const DEFAULT_DATE = 'medium';

    public const DEFAULT_NUMBER = 'plain';

    /**
     * @return list<array{value: string, label: string, example: string}>
     */
    public static function dates(): array
    {
        return self::options(self::DATES);
    }

    /**
     * @return list<array{value: string, label: string, example: string}>
     */
    public static function numbers(): array
    {
        return self::options(self::NUMBERS);
    }

    public static function isDate(?string $value): bool
    {
        return $value !== null && isset(self::DATES[$value]);
    }

    public static function isNumber(?string $value): bool
    {
        return $value !== null && isset(self::NUMBERS[$value]);
    }

    /**
     * @param  array<string, array{label: string, example: string}>  $set
     * @return list<array{value: string, label: string, example: string}>
     */
    private static function options(array $set): array
    {
        $options = [];

        foreach ($set as $value => $meta) {
            $options[] = ['value' => $value, 'label' => $meta['label'], 'example' => $meta['example']];
        }

        return $options;
    }
}
