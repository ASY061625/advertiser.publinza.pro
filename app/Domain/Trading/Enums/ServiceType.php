<?php

declare(strict_types=1);

namespace App\Domain\Trading\Enums;

enum ServiceType: string
{
    case ArticlePlacement = 'article_placement';
    case LinkInsertion = 'link_insertion';
    case Homepage = 'homepage';
    case Banner = 'banner';

    public function label(): string
    {
        return match ($this) {
            self::ArticlePlacement => 'Article placement',
            self::LinkInsertion => 'Link insertion',
            self::Homepage => 'Homepage placement',
            self::Banner => 'Banner',
        };
    }
}
