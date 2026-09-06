<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Support;

use App\Domain\Catalog\Enums\PublicationSpeed;
use App\Domain\Catalog\Models\Website;
use App\Domain\Messaging\Enums\SenderType;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\Message;
use App\Domain\Messaging\Models\MessageAttachment;
use App\Domain\Posts\Models\Post;
use Illuminate\Support\Str;

/**
 * The shapes the conversations page is sent.
 *
 * One place, because the same thread appears three times on that screen — as a
 * row in the list, as the open conversation, and as the source of the context
 * panel — and three payloads assembled separately drift into three slightly
 * different answers about the same thread.
 */
final class ConversationPresenter
{
    /**
     * What a thread row needs loaded before it is presented.
     *
     * Eloquent's lazy-loading guard only arms on a multi-row hydrate, so a
     * relation missing here passes every single-thread test and throws the
     * first time somebody has two conversations.
     */
    public const WITH = [
        'website:id,slug,domain,title',
        'post:id,website_id,status,anchor_text,target_url,price_cents,published_url,published_at,created_at',
    ];

    private const EXCERPT = 90;

    /**
     * A row in the left-hand list.
     *
     * @return array<string, mixed>
     */
    public function row(Conversation $conversation): array
    {
        $latest = $conversation->relationLoaded('messages') ? $conversation->messages->last() : null;
        $unread = (int) ($conversation->getAttribute('unread_count') ?? 0);

        return [
            'id' => $conversation->id,
            'subject' => $conversation->subject,
            // The domain is the row's title, so a thread with no site still
            // needs one. "Publinza" is who a general question is actually with,
            // and reads better than an empty cell.
            'domain' => $conversation->website?->domain ?? 'Publinza',
            'websiteSlug' => $conversation->website?->slug,
            'excerpt' => $latest === null ? '' : $this->excerpt($latest),
            'lastMessageAt' => $conversation->last_message_at?->toIso8601String(),
            'unreadCount' => $unread,
            'status' => $conversation->status->value,
            'muted' => $conversation->isMuted(),
            'post' => $conversation->post === null ? null : [
                'id' => $conversation->post->id,
                'status' => $conversation->post->status->value,
                'statusLabel' => $conversation->post->status->label(),
                'badge' => $conversation->post->status->badgeKey(),
            ],
        ];
    }

    /**
     * The open thread: its header, and every message in it.
     *
     * @return array<string, mixed>
     */
    public function thread(Conversation $conversation): array
    {
        return $this->row($conversation) + [
            'websiteTitle' => $conversation->website?->title,
            'messages' => $conversation->messages
                ->map(fn (Message $message): array => $this->message($message))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function message(Message $message): array
    {
        $mine = $message->sender_type === SenderType::User;

        return [
            'id' => $message->id,
            'senderType' => $message->sender_type->value,
            'senderName' => $mine ? 'You' : 'Publinza',
            'body' => $message->body,
            'createdAt' => $message->created_at?->toIso8601String(),
            /*
             * Two facts, not one flag.
             *
             * A message that reached the server is delivered; a message the
             * team has opened is read. Collapsing them loses the state people
             * actually want at 11pm — "it got there, nobody has looked yet" —
             * which is the difference between chasing and waiting.
             */
            'delivered' => true,
            'readAt' => $mine ? $message->read_at?->toIso8601String() : null,
            'clientToken' => $message->client_token,
            'attachments' => $message->attachments
                ->map(fn (MessageAttachment $file): array => [
                    'id' => $file->id,
                    'name' => $file->original_name,
                    'sizeBytes' => $file->size_bytes,
                    'mimeType' => $file->mime_type,
                    'isImage' => str_starts_with($file->mime_type, 'image/'),
                    'url' => "/conversations/attachments/{$file->id}",
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * The right-hand panel: the post when the thread is about one, otherwise
     * the site.
     *
     * A thread tied to a post is about that placement, and its anchor, price
     * and status timeline are what the conversation keeps referring back to.
     *
     * @return array<string, mixed>|null
     */
    public function context(Conversation $conversation): ?array
    {
        if ($conversation->post !== null) {
            return ['kind' => 'post'] + $this->post($conversation->post);
        }

        if ($conversation->website !== null) {
            return ['kind' => 'website'] + $this->website($conversation->website);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function website(Website $website): array
    {
        $metric = $website->latestMetric;

        return [
            'domain' => $website->domain,
            'title' => $website->title,
            'slug' => $website->slug,
            'category' => $website->category?->name,
            'country' => $website->country?->name,
            'language' => $website->primaryLanguage?->name,
            'metrics' => [
                ['label' => 'Monthly traffic', 'value' => $metric?->monthly_traffic, 'format' => 'compact'],
                ['label' => 'Domain rating', 'value' => $metric?->ahrefs_dr, 'format' => 'plain'],
                ['label' => 'Domain authority', 'value' => $metric?->moz_da, 'format' => 'plain'],
                ['label' => 'Spam score', 'value' => $metric?->spam_score, 'format' => 'plain'],
            ],
            'terms' => [
                'publicationLabel' => PublicationSpeed::describe($website->publication_period_hours),
                'linkType' => $website->link_type->value,
                'maxLinks' => $website->max_links,
                'minWords' => $website->min_words,
                'marksSponsored' => $website->marks_sponsored,
                // Zero months is "no guarantee", which is a real answer.
                'linkGuaranteeMonths' => $website->link_guarantee_months,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function post(Post $post): array
    {
        return [
            'id' => $post->id,
            'domain' => $post->website?->domain,
            'status' => $post->status->value,
            'statusLabel' => $post->status->label(),
            'badge' => $post->status->badgeKey(),
            'anchorText' => $post->anchor_text,
            'targetUrl' => $post->target_url,
            'priceCents' => $post->price_cents,
            'publishedUrl' => $post->published_url,
            'hasArticle' => $post->articles->isNotEmpty(),
            'timeline' => $post->statusHistory
                ->map(fn ($entry): array => [
                    'id' => $entry->id,
                    'from' => $entry->from_status,
                    'to' => $entry->to_status,
                    // Not nullsafe: PostObserver writes this row and the
                    // framework stamps it, so a history entry without a time
                    // does not exist.
                    'at' => $entry->created_at->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * One line, whoever wrote it and whatever it carried.
     *
     * A message that is only an attachment has an empty body, and a row whose
     * excerpt is blank looks like a bug. Say what is actually in it.
     */
    private function excerpt(Message $message): string
    {
        $body = trim(strip_tags($message->body));

        if ($body !== '') {
            $prefix = $message->sender_type === SenderType::User ? 'You: ' : '';

            return $prefix.Str::limit($body, self::EXCERPT);
        }

        $count = $message->attachments->count();

        return $count === 0 ? '' : sprintf('%d %s', $count, $count === 1 ? 'attachment' : 'attachments');
    }
}
