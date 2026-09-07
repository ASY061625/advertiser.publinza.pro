<?php

declare(strict_types=1);

namespace App\Domain\Identity\Support;

use App\Domain\Posts\Enums\ActorType;
use App\Domain\System\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

/**
 * Writes the security trail for an advertiser's own account.
 *
 * A thin wrapper over the existing audit_logs table rather than a second one,
 * because "who changed what" is one question and the admin side already asks
 * it here. What this adds is the vocabulary and the guarantee that a sensitive
 * change never lands without an IP beside it.
 *
 * What must never be recorded: passwords, tokens, TOTP secrets, recovery codes.
 * `changes` is rendered back to the person on their own security screen, so
 * anything written here is something they will read — which is the right test
 * for whether it belongs.
 */
final class SecurityLog
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function record(User $user, string $action, array $changes = []): AuditLog
    {
        return AuditLog::query()->create([
            'actor_type' => ActorType::User,
            'actor_id' => $user->id,
            'action' => $action,
            'auditable_type' => $user->getMorphClass(),
            'auditable_id' => $user->getKey(),
            'changes' => $changes === [] ? null : $changes,
            // Request::ip() rather than a passed-in value, so a caller cannot
            // forget it and no code path writes a security row without one.
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * The trail, most recent first, for the security tab.
     *
     * @return list<array<string, mixed>>
     */
    public function recentFor(User $user, int $limit = 20): array
    {
        return AuditLog::query()
            ->where('actor_type', ActorType::User)
            ->where('actor_id', $user->id)
            ->whereIn('action', self::ACTIONS)
            ->latest('created_at')
            ->latest('id')
            ->take($limit)
            ->get()
            ->map(static fn (AuditLog $row): array => [
                'id' => $row->id,
                'action' => $row->action,
                'label' => self::label($row->action),
                'changes' => $row->changes,
                'ip' => $row->ip_address,
                // Not nullsafe: this row was written by record() above and the
                // framework stamps it, so an audit entry without a time does
                // not exist.
                'at' => $row->created_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /** The vocabulary. Anything outside it is not an account security event. */
    public const ACTIONS = [
        'email.change_requested',
        'email.changed',
        'password.changed',
        'two_factor.enabled',
        'two_factor.disabled',
        'two_factor.recovery_codes_regenerated',
        'sessions.revoked',
        'token.created',
        'token.revoked',
        'company.updated',
        'notifications.updated',
        'account.deletion_requested',
        'account.deletion_cancelled',
    ];

    public static function label(string $action): string
    {
        return match ($action) {
            'email.change_requested' => 'Email change requested',
            'email.changed' => 'Email address changed',
            'password.changed' => 'Password changed',
            'two_factor.enabled' => 'Two-factor turned on',
            'two_factor.disabled' => 'Two-factor turned off',
            'two_factor.recovery_codes_regenerated' => 'Recovery codes regenerated',
            'sessions.revoked' => 'Sessions signed out',
            'token.created' => 'API token created',
            'token.revoked' => 'API token revoked',
            'company.updated' => 'Company details updated',
            'notifications.updated' => 'Notification settings updated',
            'account.deletion_requested' => 'Account deletion requested',
            'account.deletion_cancelled' => 'Account deletion cancelled',
            default => $action,
        };
    }
}
