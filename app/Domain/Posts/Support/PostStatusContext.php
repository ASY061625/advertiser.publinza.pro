<?php

declare(strict_types=1);

namespace App\Domain\Posts\Support;

use App\Domain\Posts\Enums\ActorType;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Who is making the next status change, and why.
 *
 * The observer needs an actor for every history row, but a status change can
 * originate from an HTTP request, a queued job or an artisan command. Rather
 * than thread an actor argument through every call site, callers declare it for
 * the duration of a block and the observer reads it.
 *
 * With nothing declared, the actor is resolved from the authenticated guards,
 * falling back to `system` — the honest answer for a scheduled job.
 */
final class PostStatusContext
{
    private static ?ActorType $actorType = null;

    private static ?int $actorId = null;

    private static ?string $note = null;

    /**
     * Runs a callback with a declared actor, restoring whatever was set before.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function actingAs(ActorType $type, ?int $id, Closure $callback): mixed
    {
        $previous = [self::$actorType, self::$actorId, self::$note];

        self::$actorType = $type;
        self::$actorId = $id;

        try {
            return $callback();
        } finally {
            [self::$actorType, self::$actorId, self::$note] = $previous;
        }
    }

    /** Attaches a note to the next transition only. */
    public static function withNote(?string $note): void
    {
        self::$note = $note;
    }

    public static function takeNote(): ?string
    {
        $note = self::$note;
        self::$note = null;

        return $note;
    }

    /**
     * @return array{ActorType, int|null}
     */
    public static function resolveActor(): array
    {
        if (self::$actorType !== null) {
            return [self::$actorType, self::$actorId];
        }

        if (Auth::guard('admin')->check()) {
            return [ActorType::Admin, Auth::guard('admin')->id()];
        }

        if (Auth::guard('web')->check()) {
            return [ActorType::User, Auth::guard('web')->id()];
        }

        return [ActorType::System, null];
    }

    /** Test helper: clears any declared actor and pending note. */
    public static function reset(): void
    {
        self::$actorType = null;
        self::$actorId = null;
        self::$note = null;
    }
}
