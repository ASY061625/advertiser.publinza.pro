<?php

declare(strict_types=1);

namespace App\Domain\Posts\Enums;

/**
 * Who caused a status transition. `system` covers scheduled jobs — the
 * verification window closing, an expired deadline — which have no actor_id.
 */
enum ActorType: string
{
    case User = 'user';
    case Admin = 'admin';
    case System = 'system';
}
