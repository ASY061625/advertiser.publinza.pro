<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Enums;

/**
 * Who wrote a message. `system` covers automated notices posted into a thread,
 * which have no sender_id.
 */
enum SenderType: string
{
    case User = 'user';
    case Admin = 'admin';
    case System = 'system';
}
