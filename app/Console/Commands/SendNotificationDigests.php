<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Notifications\Models\NotificationDigest;
use App\Domain\Notifications\Support\DigestGate;
use App\Notifications\Publinza\DigestSummaryNotification;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The other half of the fifteen-minute rule.
 *
 * DigestGate suppresses a mail and remembers what it suppressed; this comes
 * back once the window has closed and sends one email naming all of it. Without
 * this command the rule would be satisfied by silence, which is not the same
 * thing as batching.
 *
 * Runs every minute. The window is fifteen, so a summary goes out at most one
 * minute after it is due.
 */
class SendNotificationDigests extends Command
{
    protected $signature = 'notifications:send-digests';

    protected $description = 'Send one summary email per person per type for notifications held back by the 15-minute window';

    /** Named in the email, beyond which it says "and N more". */
    private const LISTED = 10;

    public function handle(DigestGate $gate): int
    {
        $sent = 0;

        foreach ($gate->due() as $digest) {
            $user = $digest->user;

            if ($user === null) {
                // The account went away while the batch waited. Clearing it
                // rather than leaving it is what stops the query returning the
                // same dead row every minute forever.
                $gate->markSent($digest);

                continue;
            }

            $items = $this->items($digest);

            if ($items === []) {
                $gate->markSent($digest);

                continue;
            }

            try {
                $user->notify(new DigestSummaryNotification($digest->type, $items, $digest->pending_count));
                $sent++;
            } catch (Throwable $e) {
                Log::warning('Notification digest could not be sent.', [
                    'digest_id' => $digest->id,
                    'reason' => $e->getMessage(),
                ]);

                // Left pending on purpose: the next minute tries again rather
                // than the batch being silently thrown away by a mail server
                // having a bad moment.
                continue;
            }

            $gate->markSent($digest);
        }

        $this->info("Sent {$sent} digest ".($sent === 1 ? 'email' : 'emails').'.');

        return self::SUCCESS;
    }

    /**
     * The stored rows for the ids the gate held back.
     *
     * Read from `notifications` rather than kept on the digest, so the summary
     * says exactly what the drawer says — one source for the sentence, and no
     * chance of an email describing something that was never recorded.
     *
     * @return list<array{title: string, body: string, href: string}>
     */
    private function items(NotificationDigest $digest): array
    {
        $ids = $digest->pending_ids ?? [];

        if ($ids === []) {
            return [];
        }

        return DatabaseNotification::query()
            ->whereIn('id', array_slice($ids, 0, self::LISTED))
            ->orderBy('created_at')
            ->get()
            ->map(static fn (DatabaseNotification $row): array => [
                'title' => (string) ($row->data['title'] ?? 'Update'),
                'body' => (string) ($row->data['body'] ?? ''),
                'href' => (string) ($row->data['href'] ?? '/notifications'),
            ])
            ->all();
    }
}
