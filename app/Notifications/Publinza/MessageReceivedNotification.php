<?php

declare(strict_types=1);

namespace App\Notifications\Publinza;

use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\Message;
use App\Domain\Notifications\Enums\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Somebody from the Publinza team replied.
 *
 * Whether this is constructed at all is still PostMessage's decision — a muted
 * thread is a separate choice from a muted account, and only the caller knows
 * about the thread.
 */
class MessageReceivedNotification extends PublinzaNotification
{
    private const EXCERPT = 140;

    public function __construct(
        private readonly int $conversationId,
        private readonly string $subject,
        private readonly string $excerpt,
    ) {
        parent::__construct();
    }

    public static function for(Conversation $conversation, Message $message): self
    {
        return new self(
            $conversation->id,
            $conversation->subject,
            Str::limit(strip_tags($message->body), self::EXCERPT),
        );
    }

    public function type(): NotificationType
    {
        return NotificationType::MessageReceived;
    }

    public function title(): string
    {
        return "Publinza replied: {$this->subject}";
    }

    public function body(): string
    {
        // The reply itself. Most are one line, and an email that makes somebody
        // sign in to read eight words trains them to ignore the next one.
        return $this->excerpt;
    }

    public function href(): string
    {
        return "/conversations?thread={$this->conversationId}";
    }

    protected function action(): string
    {
        return 'Reply in Publinza';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line("Re: {$this->subject}")
            ->line($this->excerpt)
            ->action('Reply in Publinza', $this->url($this->href()))
            ->line('You can turn these emails off in your notification settings, or mute this one conversation from its menu.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return ['conversation_id' => $this->conversationId, 'subject' => $this->subject];
    }
}
