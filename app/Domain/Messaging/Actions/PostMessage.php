<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Identity\Enums\NotificationChannel;
use App\Domain\Identity\Enums\NotificationEvent;
use App\Domain\Identity\Support\NotificationSettings;
use App\Domain\Messaging\DTOs\MessageData;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\Message;
use App\Domain\Messaging\Models\MessageAttachment;
use App\Events\ConversationActivity;
use App\Events\ShellCountsChanged;
use App\Notifications\TeamReplyNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class PostMessage
{
    /** Private, like article uploads: an attachment is nobody else's business. */
    private const DISK = 'local';

    public function __construct(private readonly NotificationSettings $settings) {}

    public function handle(Conversation $conversation, MessageData $data): Message
    {
        // Idempotency first, and outside the transaction: a retry of a request
        // that already succeeded should return the message it made, not open a
        // second write that the unique index will only reject at commit.
        if ($data->clientToken !== null) {
            $existing = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('client_token', $data->clientToken)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $message = DB::transaction(function () use ($conversation, $data): Message {
            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_type' => $data->senderType,
                'sender_id' => $data->senderId,
                'body' => $data->body,
                'client_token' => $data->clientToken,
            ]);

            foreach ($data->attachments as $file) {
                $this->attach($message, $conversation, $file);
            }

            // Denormalised so inbox lists sort without touching messages.
            $conversation->update(['last_message_at' => $message->created_at]);

            return $message;
        });

        $this->announce($conversation, $message);

        return $message->load('attachments');
    }

    private function attach(Message $message, Conversation $conversation, UploadedFile $file): void
    {
        MessageAttachment::query()->create([
            'message_id' => $message->id,
            'disk' => self::DISK,
            'path' => $file->store("conversations/{$conversation->user_id}/{$conversation->id}", self::DISK),
            // The name the sender saw, kept apart from the stored path: the
            // path is hashed so two people uploading "article.docx" cannot
            // collide, and the chip still has to say "article.docx".
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
        ]);
    }

    /**
     * Everything that happens *because* a message was posted.
     *
     * Here rather than in a controller, so a reply posted from the admin panel,
     * a console command or a webhook gets the same broadcast and the same email
     * as one posted from a form. A side effect that only fires on one code path
     * is a side effect that will be missing from the next code path.
     */
    private function announce(Conversation $conversation, Message $message): void
    {
        $advertiser = $conversation->user;

        if ($advertiser === null) {
            return;
        }

        // The advertiser's own message changes nothing for them: it is already
        // on their screen, and their unread count does not move.
        if (! $message->isFromTeam()) {
            return;
        }

        ConversationActivity::dispatch($advertiser, $conversation);
        ShellCountsChanged::dispatch($advertiser, ['conversations']);

        /*
         * A muted thread and an account that has turned replies off are two
         * different decisions with the same answer, and both are the reader's
         * to make. A system notice is not a reply, and is not worth an email.
         *
         * The account-level answer comes from NotificationSettings rather than
         * from the `notify_replies` column this used to read: the profile's
         * notification matrix is the one place that decides what reaches
         * somebody, and two systems answering that question is how a person
         * ends up muted from something they never muted.
         */
        if ($message->sender_type->value === 'system' || $conversation->isMuted()) {
            return;
        }

        if (! $this->settings->wants($advertiser, NotificationEvent::NewMessage, NotificationChannel::Email)) {
            return;
        }

        $advertiser->notify(new TeamReplyNotification($conversation, $message));
    }
}
