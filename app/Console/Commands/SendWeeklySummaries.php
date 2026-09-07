<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Models\User;
use App\Notifications\Publinza\WeeklySummaryNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * "Your week on Publinza", once a week.
 *
 * Only to accounts that have something to summarise. A weekly digest sent to
 * somebody with no posts and no spend is the definition of the email that gets
 * a filter rule written for it, and it would take the rest of our mail with it.
 */
class SendWeeklySummaries extends Command
{
    protected $signature = 'notifications:weekly-summary';

    protected $description = 'Send each active advertiser a summary of their week';

    public function handle(): int
    {
        $since = now()->subWeek();
        $sent = 0;

        User::query()
            ->where('status', UserStatus::Active)
            ->whereNull('deletion_requested_at')
            ->chunkById(200, function ($users) use ($since, &$sent): void {
                foreach ($users as $user) {
                    $published = Post::query()
                        ->where('user_id', $user->id)
                        ->whereIn('status', [PostStatus::Posted, PostStatus::Completed])
                        ->where('published_at', '>=', $since)
                        ->count();

                    $inProgress = Post::query()
                        ->where('user_id', $user->id)
                        ->whereIn('status', [PostStatus::New, PostStatus::InProgress, PostStatus::ContentReview])
                        ->count();

                    // Only what was actually charged in the window. Frozen
                    // money is not spent, and a summary that counts it as spend
                    // disagrees with the balance page.
                    $spent = (int) DB::table('transactions')
                        ->join('wallets', 'wallets.id', '=', 'transactions.wallet_id')
                        ->where('wallets.user_id', $user->id)
                        ->where('transactions.type', 'charge')
                        ->where('transactions.created_at', '>=', $since)
                        ->sum(DB::raw('abs(transactions.amount_cents)'));

                    if ($published === 0 && $inProgress === 0 && $spent === 0) {
                        continue;
                    }

                    $user->notify(new WeeklySummaryNotification($published, $inProgress, $spent));
                    $sent++;
                }
            });

        $this->info("Sent {$sent} weekly ".($sent === 1 ? 'summary' : 'summaries').'.');

        return self::SUCCESS;
    }
}
