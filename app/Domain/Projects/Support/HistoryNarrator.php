<?php

declare(strict_types=1);

namespace App\Domain\Projects\Support;

use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use stdClass;

/**
 * Turns the union's rows into sentences.
 *
 * The descriptions are written here rather than stored, because the record is
 * the fact and the sentence is a rendering of it: "Category changed from
 * Finance to Technology" should be able to improve without rewriting history.
 *
 * Everything is resolved in bulk. Fifty rows referencing fifty posts is one
 * query for the posts, not fifty — a timeline that does N+1 per entry is a
 * timeline nobody scrolls twice.
 */
final class HistoryNarrator
{
    /**
     * @param  Collection<int, stdClass>  $rows
     * @return list<array<string, mixed>>
     */
    public function describe(Project $project, Collection $rows): array
    {
        $posts = $this->posts($rows);
        $actors = $this->actors($rows);

        return $rows->map(function (stdClass $row) use ($project, $posts, $actors): array {
            $family = $this->family($row);
            $post = $posts->get((int) ($row->subject_id ?? 0));

            return [
                'id' => $row->source.'-'.$row->source_id,
                'family' => $family,
                'eventKey' => (string) $row->event_key,
                'occurredAt' => (string) $row->occurred_at,
                'actor' => $this->actorName((string) $row->actor_type, $row->actor_id, $actors),
                'description' => $this->description($row, $family, $project, $post),
                'detail' => $this->detail($row, $family, $post),
            ];
        })->all();
    }

    /**
     * The audit table carries two families; everything else is its own.
     */
    private function family(stdClass $row): string
    {
        if ($row->source !== 'audit') {
            return (string) $row->source;
        }

        return str_starts_with((string) $row->event_key, 'project.') ? 'project' : 'folder';
    }

    private function description(stdClass $row, string $family, Project $project, ?Post $post): string
    {
        return match ($family) {
            'project' => $this->projectDescription($row, $project),
            'folder' => $this->folderDescription($row),
            'post' => $this->postDescription($row, $post),
            'money' => $this->moneyDescription($row, $post),
            default => $this->messageDescription($row),
        };
    }

    private function projectDescription(stdClass $row, Project $project): string
    {
        $changes = $this->payload($row);
        $key = (string) $row->event_key;

        return match (true) {
            $key === 'project.archived' => 'Project archived',
            $key === 'project.restored' => 'Project restored',
            $key === 'project.deleted' => 'Project deleted',
            $key === 'project.created' => "Project “{$project->name}” created",
            isset($changes['field']) => ucfirst(self::FIELD_LABELS[$changes['field']] ?? (string) $changes['field'])
                .' changed',
            default => 'Project settings changed',
        };
    }

    private function folderDescription(stdClass $row): string
    {
        $changes = $this->payload($row);
        $name = (string) ($changes['folder'] ?? 'A folder');

        return match ((string) $row->event_key) {
            'folder.added' => "Folder “{$name}” added",
            'folder.removed' => "Folder “{$name}” removed",
            default => isset($changes['renamed_from']) && $changes['renamed_from'] !== null
                ? "Folder renamed from “{$changes['renamed_from']}” to “{$name}”"
                : "Folder “{$name}” edited",
        };
    }

    private function postDescription(stdClass $row, ?Post $post): string
    {
        $domain = $post?->website?->domain;
        $where = $domain === null ? '' : " on {$domain}";

        $status = PostStatus::tryFrom((string) $row->event_key);

        return match ($status) {
            PostStatus::Draft => "Post drafted{$where}",
            PostStatus::New => "Post ordered{$where}",
            PostStatus::InProgress => "Article started{$where}",
            PostStatus::ContentReview => "Article sent for review{$where}",
            PostStatus::Posted => "Post published{$where}",
            PostStatus::Completed => "Placement verified{$where}",
            PostStatus::Rejected => "Article rejected{$where}",
            PostStatus::Cancelled => "Post cancelled{$where}",
            PostStatus::Refunded => "Post refunded{$where}",
            default => "Post updated{$where}",
        };
    }

    private function moneyDescription(stdClass $row, ?Post $post): string
    {
        $amount = '$'.number_format(abs((int) ($row->amount_cents ?? 0)) / 100, 2);
        $domain = $post?->website?->domain;
        $where = $domain === null ? '' : " for {$domain}";

        return match ((string) $row->event_key) {
            'freeze' => "{$amount} frozen{$where}",
            'unfreeze' => "{$amount} released{$where}",
            'charge' => "{$amount} charged{$where}",
            'refund' => "{$amount} refunded{$where}",
            'deposit' => "{$amount} added to your balance",
            'bonus' => "{$amount} bonus applied",
            default => "{$amount} adjustment{$where}",
        };
    }

    private function messageDescription(stdClass $row): string
    {
        $subject = (string) ($row->payload ?? 'a conversation');

        return (string) $row->event_key === 'user'
            ? "You replied in “{$subject}”"
            : "Reply received in “{$subject}”";
    }

    /**
     * The expandable panel, or null when there is nothing more to show.
     *
     * @return array<string, mixed>|null
     */
    private function detail(stdClass $row, string $family, ?Post $post): ?array
    {
        if ($family === 'post' || $family === 'money') {
            if ($post === null) {
                return null;
            }

            return [
                'kind' => 'post',
                'postId' => $post->id,
                'domain' => $post->website?->domain,
                'anchorText' => $post->anchor_text,
                'targetUrl' => $post->target_url,
                'priceCents' => $post->price_cents,
                'note' => $family === 'post' ? $row->payload : null,
            ];
        }

        $changes = $this->payload($row);

        if ($changes === [] || ! isset($changes['field'])) {
            return null;
        }

        $field = (string) $changes['field'];

        // The brief is prose, so it gets a word-level diff; everything else is
        // a value that was one thing and is now another.
        return [
            'kind' => $field === 'brief' ? 'text-diff' : 'fields',
            'rows' => [[
                'field' => self::FIELD_LABELS[$field] ?? ucfirst($field),
                'from' => $this->stringify($changes['from'] ?? null),
                'to' => $this->stringify($changes['to'] ?? null),
            ]],
        ];
    }

    /** The words for the columns the audit trail records by name. */
    private const FIELD_LABELS = [
        'name' => 'name',
        'website' => 'promoted website',
        'category' => 'category',
        'colour' => 'colour',
        'brief' => 'publisher brief',
        'topics' => 'sensitive topics',
        'countries' => 'countries',
        'languages' => 'languages',
    ];

    private function stringify(mixed $value): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        return is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(stdClass $row): array
    {
        if (! is_string($row->payload) || $row->payload === '') {
            return [];
        }

        $decoded = json_decode($row->payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return Collection<int, Post>
     */
    private function posts(Collection $rows): Collection
    {
        $ids = $rows->pluck('subject_id')->filter()->map(static fn (mixed $id): int => (int) $id)->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Post::query()
            ->whereIn('id', $ids->all())
            ->with('website:id,domain')
            ->get(['id', 'website_id', 'anchor_text', 'target_url', 'price_cents'])
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return array<string, string>
     */
    private function actors(Collection $rows): array
    {
        // Only advertisers are looked up. Admins are never named to an
        // advertiser — who at Publinza handled a placement is not their
        // business, and putting a staff member's name on a permanent log is
        // not a decision to make by accident — so there is nothing to fetch.
        $userIds = $rows->where('actor_type', 'user')->pluck('actor_id')->filter()->unique();

        $names = [];

        foreach (User::query()->whereIn('id', $userIds->all())->get(['id', 'name']) as $user) {
            $names['user:'.$user->id] = $user->name;
        }

        return $names;
    }

    /**
     * @param  array<string, string>  $actors
     */
    private function actorName(string $type, mixed $id, array $actors): string
    {
        return match ($type) {
            'user' => $actors['user:'.(int) $id] ?? 'You',
            'admin' => 'Publinza team',
            default => 'System',
        };
    }
}
