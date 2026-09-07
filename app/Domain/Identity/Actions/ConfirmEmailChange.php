<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Support\SecurityLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ConfirmEmailChange
{
    public function __construct(private readonly SecurityLog $log) {}

    /**
     * @return bool False when the token is wrong, expired, or the address has
     *              been claimed by somebody else since the link was sent.
     */
    public function handle(User $user, string $token): bool
    {
        if ($user->pending_email === null || $user->pending_email_token === null) {
            return false;
        }

        // hash_equals, not ===: this is a secret compared against user input,
        // and the timing of a mismatch should not say where it diverged.
        if (! hash_equals($user->pending_email_token, hash('sha256', $token))) {
            return false;
        }

        if ($user->pending_email_sent_at === null
            || $user->pending_email_sent_at->addHours(RequestEmailChange::TTL_HOURS)->isPast()) {
            return false;
        }

        // Somebody else may have signed up with it in the meantime. The unique
        // index would catch it, but as a 500 rather than an explanation.
        $taken = User::query()
            ->where('email', $user->pending_email)
            ->whereKeyNot($user->getKey())
            ->exists();

        if ($taken) {
            return false;
        }

        $previous = $user->email;

        DB::transaction(function () use ($user): void {
            $user->forceFill([
                'email' => $user->pending_email,
                // Proven by this very click, so it is verified by definition.
                'email_verified_at' => now(),
                'pending_email' => null,
                'pending_email_token' => null,
                'pending_email_sent_at' => null,
            ])->save();
        });

        $this->log->record($user, 'email.changed', ['from' => $previous, 'to' => $user->email]);

        return true;
    }
}
