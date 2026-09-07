<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum NotificationChannel: string
{
    case Email = 'email';
    case InApp = 'in_app';
    case Push = 'push';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::InApp => 'In-app',
            self::Push => 'Browser push',
        };
    }

    /** The database column this channel is stored in. */
    public function column(): string
    {
        return $this->value;
    }
}
