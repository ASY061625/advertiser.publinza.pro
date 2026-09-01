<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Archived => 'Archived',
        };
    }
}
