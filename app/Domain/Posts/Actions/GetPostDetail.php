<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Messaging\Models\Message;
use App\Domain\Posts\Models\Post;
use App\Support\HtmlSanitizer;

/**
 * Everything the row drawer's four tabs need, in one request.
 *
 * One request rather than four: the drawer opens on a row click and all four
 * tabs have to be instant, and a post's article and history are small enough
 * that fetching them lazily would cost more in round trips than it saves.
 */
final class GetPostDetail
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Post $post): array
    {
        $post->load([
            'website:id,domain,title,country_id,primary_language_id,category_id',
            'website.latestMetric',
            'website.country:id,name',
            'project:id,name,website_url',
            'folder:id,name',
            'articles',
            'statusHistory',
            'conversations.messages.attachments',
        ]);

        $article = $post->articles->last();

        return [
            'id' => $post->id,
            'status' => $post->status->value,
            'statusLabel' => $post->status->label(),
            'badge' => $post->status->badgeKey(),
            'canCancel' => $post->status->isPrePosted(),

            'details' => [
                'domain' => $post->website?->domain,
                'websiteTitle' => $post->website?->title,
                'country' => $post->website?->country?->name,
                'dr' => $post->website?->latestMetric?->ahrefs_dr,
                'traffic' => $post->website?->latestMetric?->monthly_traffic,
                'project' => $post->project?->name,
                'projectUrl' => $post->project?->website_url,
                'folder' => $post->folder?->name,
                'anchorText' => $post->anchor_text,
                'targetUrl' => $post->target_url,
                'contentMode' => $post->content_mode->label(),
                'priceCents' => $post->price_cents,
                'createdAt' => $post->created_at?->toIso8601String(),
                'publishedAt' => $post->published_at?->toIso8601String(),
                'deadlineAt' => $post->deadline_at?->toIso8601String(),
                'publishedUrl' => $post->published_url,
                'rejectionReason' => $post->rejection_reason,
            ],

            'article' => $article === null ? null : [
                'id' => $article->id,
                'title' => $article->title,
                'wordCount' => $article->word_count,
                'version' => $article->version,
                'versions' => $post->articles->count(),
                'submittedBy' => $article->submitted_by,
                'approvedAt' => $article->approved_at?->toIso8601String(),
                // Publisher-authored, so hostile until sanitised. It is
                // rendered unescaped in the drawer inside the advertiser's
                // authenticated session — the one place where a stray <script>
                // would run with their cookies.
                'bodyHtml' => HtmlSanitizer::clean($article->body_html),
                'hasFile' => $article->file_path !== null,
            ],

            'messages' => $post->conversations
                ->flatMap(fn ($conversation) => $conversation->messages->map(fn (Message $message): array => [
                    'id' => $message->id,
                    'conversationId' => $conversation->id,
                    'subject' => $conversation->subject,
                    'senderType' => $message->sender_type,
                    'body' => $message->body,
                    'readAt' => $message->read_at?->toIso8601String(),
                    'createdAt' => $message->created_at?->toIso8601String(),
                    'attachments' => $message->attachments->map(fn ($file): array => [
                        'id' => $file->id,
                        'name' => $file->original_name,
                        'sizeBytes' => $file->size_bytes,
                    ])->values()->all(),
                ]))
                ->sortBy('createdAt')
                ->values()
                ->all(),

            'history' => $post->statusHistory->map(fn ($entry): array => [
                'id' => $entry->id,
                'from' => $entry->from_status,
                'to' => $entry->to_status,
                'actorType' => $entry->actor_type,
                'note' => $entry->note,
                'createdAt' => $entry->created_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
