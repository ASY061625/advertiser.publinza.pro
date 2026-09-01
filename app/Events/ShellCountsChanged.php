<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells one advertiser's open tabs that a badge count moved.
 *
 * Deliberately carries no counts. The payload names what changed and the client
 * re-reads `/shell/counts`, so two tabs cannot disagree because their events
 * arrived out of order, and a stale event cannot paint a number that is no
 * longer true.
 *
 * With BROADCAST_CONNECTION=null this dispatches into the null driver and the
 * client's 60-second poll carries the counts instead.
 */
class ShellCountsChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  list<string>  $scopes  Any of: cart, conversations, changelog, favorites.
     */
    public function __construct(
        public readonly User $user,
        public readonly array $scopes = [],
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("advertiser.{$this->user->id}")];
    }

    public function broadcastAs(): string
    {
        return 'shell.counts';
    }

    /**
     * @return array{scopes: list<string>}
     */
    public function broadcastWith(): array
    {
        return ['scopes' => $this->scopes];
    }
}
