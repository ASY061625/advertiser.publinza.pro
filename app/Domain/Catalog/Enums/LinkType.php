<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum LinkType: string
{
    case Dofollow = 'dofollow';
    case Nofollow = 'nofollow';

    public function label(): string
    {
        return match ($this) {
            self::Dofollow => 'Dofollow',
            self::Nofollow => 'Nofollow',
        };
    }
}
