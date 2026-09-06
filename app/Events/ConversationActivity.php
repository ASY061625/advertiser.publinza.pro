<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Messaging\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells one advertiser's open tabs that something happened in a thread.
 *
 * Carries the thread id and nothing else — no message body, no counts. Same
 * discipline as ShellCountsChanged: the client re-reads the page, so two tabs
 * cannot disagree because their events arrived out of order, and a stale event
 * cannot paint a message that has since been edited or a thread that has since
 * been closed.
 *
 * It also means this event is safe to broadcast without deciding what the
 * receiving tab is allowed to see. The re-read goes through the controller and
 * the policy, which already answer that.
 */
class ConversationActivity implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Conversation $conversation,
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
        return 'conversation.activity';
    }

    /**
     * @return array{threadId: int}
     */
    public function broadcastWith(): array
    {
        return ['threadId' => $this->conversation->id];
    }
}
