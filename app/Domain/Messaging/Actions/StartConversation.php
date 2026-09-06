<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Catalog\Models\Website;
use App\Domain\Messaging\DTOs\MessageData;
use App\Domain\Messaging\Enums\SenderType;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Posts\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Opens a thread about a website or a post.
 *
 * Threads are always advertiser ↔ Publinza, because Publinza owns every site in
 * the catalog. What varies is the *subject* — which site, which placement — and
 * that is what this pins down, so a reply three weeks later still has the
 * context that made the question make sense.
 */
final class StartConversation
{
    public function __construct(private readonly PostMessage $postMessage) {}

    /**
     * @param  list<UploadedFile>  $attachments
     */
    public function handle(
        User $user,
        string $subject,
        string $body,
        ?Website $website = null,
        ?Post $post = null,
        array $attachments = [],
    ): Conversation {
        // A post implies its site. Asking somebody to pick both, and then
        // storing whichever they got wrong, is how a thread ends up filed under
        // a site the post was never on.
        $website ??= $post?->website;

        $conversation = DB::transaction(fn (): Conversation => Conversation::query()->create([
            'user_id' => $user->id,
            'website_id' => $website?->id,
            'post_id' => $post?->id,
            'subject' => $subject,
            'last_message_at' => now(),
        ]));

        $this->postMessage->handle($conversation, new MessageData(
            body: $body,
            senderType: SenderType::User,
            senderId: $user->id,
            attachments: $attachments,
        ));

        return $conversation->fresh() ?? $conversation;
    }
}
