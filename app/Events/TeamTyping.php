<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Messaging\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A Publinza teammate is writing in one of this advertiser's threads.
 *
 * ShouldBroadcastNow, not ShouldBroadcast: a typing indicator that arrives
 * after the message it was announcing is worse than no indicator, and a queue
 * worker is exactly the delay that produces that.
 *
 * Nothing is persisted. If the browser is not listening at this instant the
 * event is simply gone, which is the correct behaviour for something that
 * describes the present tense.
 *
 * Dispatched from the admin messaging surface. The advertiser side listens and
 * expires the indicator on its own timer, so a teammate who closes the tab
 * mid-sentence does not leave "Publinza is typing…" on screen forever.
 */
class TeamTyping implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly User $user,
        public readonly Conversation $conversation,
        public readonly string $name = 'Publinza',
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
        return 'conversation.typing';
    }

    /**
     * @return array{threadId: int, name: string}
     */
    public function broadcastWith(): array
    {
        return ['threadId' => $this->conversation->id, 'name' => $this->name];
    }
}
