<?php

declare(strict_types=1);

namespace App\Domain\System\Enums;

/**
 * What a changelog entry is: New, Improved or Fixed.
 *
 * Three, and only three. A changelog with eight categories is one nobody scans,
 * and the reader's question is always the same — is this something I can now
 * do, something that got better, or something that was broken.
 */
enum ChangelogType: string
{
    case New = 'new';
    case Improved = 'improved';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Improved => 'Improved',
            self::Fixed => 'Fixed',
        };
    }

    /**
     * The chip fill, named as the spec names it.
     *
     * These map onto existing status tokens rather than new ones: brand-blue-50
     * is `--status-new-bg`, teal-50 is `--status-posted-bg`, and surface-sunken
     * is `--status-draft-bg`. Three more colour variables for three chips would
     * be three more things to keep in step with the palette.
     */
    public function chip(): string
    {
        return match ($this) {
            self::New => 'new',
            self::Improved => 'improved',
            self::Fixed => 'fixed',
        };
    }

    /**
     * Reads a value written before the vocabulary settled.
     *
     * The column used to be `category` and held 'improvement' and 'fix'. Rows
     * written then are still rows, and an entry that renders as "Update"
     * because of a word that changed is worse than a two-line map.
     */
    public static function parse(?string $value): self
    {
        return match ($value) {
            'new' => self::New,
            'fix', 'fixed' => self::Fixed,
            default => self::Improved,
        };
    }
}
