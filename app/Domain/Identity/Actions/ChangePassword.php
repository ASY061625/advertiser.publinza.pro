<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Support\SecurityLog;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;

/**
 * Changes the password and kicks every other session out.
 *
 * The sign-out is the point, not a nicety. Someone changing their password
 * because they think it was stolen has to be able to assume that whoever had it
 * is now out — a password change that leaves an attacker's session alive is
 * theatre. The current session is spared, because logging somebody out of the
 * form they just submitted is its own kind of failure.
 *
 * The email is not optional either: if the person doing this is not the owner,
 * the mail is the only way the owner finds out in time to act.
 */
final class ChangePassword
{
    public function __construct(private readonly SecurityLog $log) {}

    public function handle(User $user, string $plain, ?string $keepSessionId = null): int
    {
        $revoked = DB::transaction(function () use ($user, $plain, $keepSessionId): int {
            $user->forceFill([
                'password' => Hash::make($plain),
                // A new password invalidates "remember me" on every browser
                // that held one, which is a second door onto the same account.
                'remember_token' => null,
            ])->save();

            return $this->revokeOtherSessions($user, $keepSessionId);
        });

        $this->log->record($user, 'password.changed', ['sessionsSignedOut' => $revoked]);

        $user->notify(new PasswordChangedNotification(Request::ip(), $revoked));

        return $revoked;
    }

    /**
     * @return int How many sessions were ended.
     */
    private function revokeOtherSessions(User $user, ?string $keepSessionId): int
    {
        // Only meaningful on the database session driver. Where sessions live
        // in Redis or files there is no table to sweep, and the rotated
        // remember_token plus Laravel's own password-hash check in
        // AuthenticateSession is what ends them.
        if (config('session.driver') !== 'database') {
            return 0;
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->when($keepSessionId !== null, fn ($q) => $q->where('id', '!=', $keepSessionId))
            ->delete();
    }
}
