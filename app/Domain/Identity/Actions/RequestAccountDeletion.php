<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Billing\Models\Wallet;
use App\Domain\Identity\Support\SecurityLog;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Models\User;

/**
 * Closing an account, or explaining why it cannot be closed yet.
 *
 * Two things block it, and both are about somebody else's money or work being
 * in flight:
 *
 *  - An active post. A publisher is part-way through writing or publishing it,
 *    and deleting the account that ordered it strands them.
 *  - Frozen funds. That money is the advertiser's, committed to orders that
 *    have not settled. Deleting the account while it is held is how money gets
 *    lost, and "we will sort it out afterwards" is not a plan anybody should
 *    have to trust.
 *
 * Nothing is destroyed here. The account is marked and soft-deleted after a
 * 30-day window, during which signing in cancels it — which is the recovery
 * path for the person who clicks this at 2am and regrets it at 9.
 */
final class RequestAccountDeletion
{
    public const RETENTION_DAYS = 30;

    public function __construct(private readonly SecurityLog $log) {}

    /**
     * Why the account cannot be closed right now, if it cannot.
     *
     * @return array{activePosts: int, frozenCents: int, blocked: bool}
     */
    public function blockers(User $user): array
    {
        $active = Post::query()
            ->where('user_id', $user->id)
            ->whereIn('status', array_filter(
                PostStatus::cases(),
                static fn (PostStatus $status): bool => ! $status->isTerminal() && $status !== PostStatus::Draft,
            ))
            ->count();

        $frozen = (int) Wallet::query()->where('user_id', $user->id)->value('frozen_cents');

        return [
            'activePosts' => $active,
            'frozenCents' => $frozen,
            'blocked' => $active > 0 || $frozen > 0,
        ];
    }

    public function request(User $user): void
    {
        $user->forceFill(['deletion_requested_at' => now()])->save();

        $this->log->record($user, 'account.deletion_requested', [
            'retentionDays' => self::RETENTION_DAYS,
            'deletesOn' => now()->addDays(self::RETENTION_DAYS)->toDateString(),
        ]);
    }

    public function cancel(User $user): void
    {
        $user->forceFill(['deletion_requested_at' => null])->save();

        $this->log->record($user, 'account.deletion_cancelled');
    }
}
