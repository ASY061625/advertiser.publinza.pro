<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Models\User;
use App\Notifications\EmailChangeVerificationNotification;
use Illuminate\Support\Str;

/**
 * Starts a change of email address.
 *
 * The new address goes to `pending_email` and stays there. The account keeps
 * signing in with, and keeps receiving password resets at, the address that was
 * already proven — until the new one proves itself too. Two failure modes this
 * closes:
 *
 *  - A typo. Writing an unproven address straight into `email` locks somebody
 *    out of their own account with no way back that does not involve support.
 *  - A hijacked session. Changing the address a reset goes to is the first
 *    thing an attacker does; requiring the new inbox to confirm means they need
 *    that inbox as well as the session.
 *
 * The confirmation link goes to the *new* address, and a notice goes to the old
 * one, so the legitimate owner hears about it either way.
 */
final class RequestEmailChange
{
    /** Long enough that a stale link is useless, short enough to still arrive. */
    public const TTL_HOURS = 2;

    public function handle(User $user, string $newEmail): void
    {
        $token = Str::random(64);

        $user->forceFill([
            'pending_email' => mb_strtolower(trim($newEmail)),
            'pending_email_token' => hash('sha256', $token),
            'pending_email_sent_at' => now(),
        ])->save();

        // The notification routes itself to the pending address — see its
        // routeNotificationForMail. Sent through the user so it still lands in
        // the database channel against the right account.
        $user->notify(new EmailChangeVerificationNotification($user->pending_email, $token));
    }

    public function cancel(User $user): void
    {
        $user->forceFill([
            'pending_email' => null,
            'pending_email_token' => null,
            'pending_email_sent_at' => null,
        ])->save();
    }
}
