<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Domain\Billing\Enums\TransactionType;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\DTOs\HistoryCursor;
use App\Domain\Projects\DTOs\HistoryFilters;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Support\HistoryNarrator;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Everything that has happened to one project, from the records that happened.
 *
 * Five append-only tables already hold this: audit_logs, post_status_history,
 * transactions, messages, and the conversations around them. They are unioned
 * at read time rather than copied into an events table, for two reasons — a
 * second copy is a second thing that can drift from the record it describes,
 * and a table added today would have nothing to say about anything that
 * happened before it.
 *
 * There is no write path in this class at all. That is what makes the tab
 * immutable: not a flag on a row, but the absence of anywhere to edit.
 *
 * No SQL string-building anywhere here. `||` concatenates on SQLite and means
 * OR on MySQL, and CONCAT() is the other way round — so every column is
 * selected raw and anything composite is assembled in PHP, where it means one
 * thing on both.
 */
final class GetProjectHistory
{
    public function __construct(private readonly HistoryNarrator $narrator) {}

    /**
     * @return array{events: list<array<string, mixed>>, total: int, hasMore: bool, nextCursor: string|null}
     */
    public function handle(Project $project, HistoryFilters $filters): array
    {
        $union = $this->union($project, $filters);

        if ($union === null) {
            return ['events' => [], 'total' => 0, 'hasMore' => false, 'nextCursor' => null];
        }

        // Counted without the cursor: the label under the filters says how
        // much history matches them, not how much of it is still below the
        // reader — a number that fell as you scrolled would be describing the
        // scroll position rather than the log.
        $total = (int) DB::query()->fromSub($union, 'history')->count();

        $query = DB::query()
            ->fromSub($union, 'history')
            ->orderByDesc('occurred_at')
            // Rows from four tables routinely share a timestamp to the second.
            // The tiebreak is what makes the order total, and the cursor below
            // relies on it being exactly this one.
            ->orderByDesc('source')
            ->orderByDesc('source_id');

        if ($filters->cursor !== null) {
            $this->seek($query, $filters->cursor);
        }

        // One row past the page, to learn whether there is a next one without
        // a second count against a moving log.
        $rows = $query->limit(HistoryFilters::PER_PAGE + 1)->get();

        $hasMore = $rows->count() > HistoryFilters::PER_PAGE;
        $rows = $rows->take(HistoryFilters::PER_PAGE);

        $events = $this->narrator->describe($project, $rows);
        $last = $events === [] ? null : $events[count($events) - 1];

        return [
            'events' => $events,
            'total' => $total,
            'hasMore' => $hasMore,
            'nextCursor' => $hasMore && $last !== null ? HistoryCursor::after($last)->toQuery() : null,
        ];
    }

    /**
     * Reads from a position in the log rather than an offset into it.
     *
     * The predicate is the sort, spelled out: strictly older, or the same
     * instant and further down the tiebreak. Written as a row comparison
     * `(occurred_at, source, source_id) < (…)` this would be one line, but
     * SQLite and MySQL disagree about row values, so it is nested by hand.
     */
    private function seek(Builder $query, HistoryCursor $cursor): void
    {
        if ($cursor->source === null) {
            // A jump to a date: the last instant of that day, inclusive, since
            // there is no row here to sit below.
            $query->where('occurred_at', '<=', $cursor->occurredAt);

            return;
        }

        $query->where(function (Builder $outer) use ($cursor): void {
            $outer->where('occurred_at', '<', $cursor->occurredAt)
                ->orWhere(function (Builder $sameInstant) use ($cursor): void {
                    $sameInstant->where('occurred_at', '=', $cursor->occurredAt)
                        ->where(function (Builder $tie) use ($cursor): void {
                            $tie->where('source', '<', $cursor->source)
                                ->orWhere(function (Builder $sameSource) use ($cursor): void {
                                    $sameSource->where('source', '=', $cursor->source)
                                        ->where('source_id', '<', $cursor->sourceId);
                                });
                        });
                });
        });
    }

    /** Every source, normalised to the same nine columns and stacked. */
    private function union(Project $project, HistoryFilters $filters): ?BuilderContract
    {
        $parts = array_values(array_filter([
            $filters->wants('project') || $filters->wants('folder') ? $this->auditRows($project, $filters) : null,
            $filters->wants('post') ? $this->postRows($project, $filters) : null,
            $filters->wants('money') ? $this->moneyRows($project, $filters) : null,
            $filters->wants('message') ? $this->messageRows($project, $filters) : null,
        ]));

        if ($parts === []) {
            return null;
        }

        $union = array_shift($parts);

        foreach ($parts as $part) {
            $union = $union->unionAll($part);
        }

        // The window and the actor apply to every source the same way, so they
        // are applied once to the stacked result rather than four times.
        return DB::query()
            ->fromSub($union, 'events')
            ->when($filters->from !== null, fn (Builder $q) => $q->where('occurred_at', '>=', $filters->from.' 00:00:00'))
            ->when($filters->to !== null, fn (Builder $q) => $q->where('occurred_at', '<=', $filters->to.' 23:59:59'))
            ->when($filters->actor !== null, fn (Builder $q) => $q->where('actor_type', $filters->actor));
    }

    private function auditRows(Project $project, HistoryFilters $filters): Builder
    {
        return DB::table('audit_logs')
            ->where('auditable_type', Project::class)
            ->where('auditable_id', $project->id)
            // Two families share this table, so a filter for one of them has
            // to narrow it here rather than after the union.
            ->when(! $filters->wants('folder'), fn (Builder $q) => $q->where('action', 'like', 'project.%'))
            ->when(! $filters->wants('project'), fn (Builder $q) => $q->where('action', 'not like', 'project.%'))
            ->when($filters->search !== null, fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner
                    ->where('action', 'like', self::term($filters))
                    ->orWhere('changes', 'like', self::term($filters)),
            ))
            ->select([
                DB::raw("'audit' as source"),
                'audit_logs.id as source_id',
                'audit_logs.action as event_key',
                'audit_logs.actor_type',
                'audit_logs.actor_id',
                DB::raw('null as subject_id'),
                'audit_logs.changes as payload',
                DB::raw('null as amount_cents'),
                'audit_logs.created_at as occurred_at',
            ]);
    }

    private function postRows(Project $project, HistoryFilters $filters): Builder
    {
        return DB::table('post_status_history')
            ->join('posts', 'posts.id', '=', 'post_status_history.post_id')
            ->leftJoin('websites', 'websites.id', '=', 'posts.website_id')
            ->where('posts.project_id', $project->id)
            ->whereNull('posts.deleted_at')
            ->when($filters->search !== null, fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner
                    ->where('websites.domain', 'like', self::term($filters))
                    ->orWhere('posts.anchor_text', 'like', self::term($filters))
                    ->orWhere('post_status_history.to_status', 'like', self::term($filters))
                    ->orWhere('post_status_history.note', 'like', self::term($filters)),
            ))
            ->select([
                DB::raw("'post' as source"),
                'post_status_history.id as source_id',
                'post_status_history.to_status as event_key',
                'post_status_history.actor_type',
                'post_status_history.actor_id',
                'post_status_history.post_id as subject_id',
                'post_status_history.note as payload',
                DB::raw('null as amount_cents'),
                'post_status_history.created_at as occurred_at',
            ]);
    }

    private function moneyRows(Project $project, HistoryFilters $filters): Builder
    {
        return DB::table('transactions')
            ->join('posts', function ($join): void {
                // The full class name, because there is no morph map: the
                // wallet stores getMorphClass() and a hand-written 'post'
                // here would match nothing and quietly drop every money event.
                $join->on('posts.id', '=', 'transactions.reference_id')
                    ->where('transactions.reference_type', '=', Post::class);
            })
            ->where('posts.project_id', $project->id)
            ->whereNull('posts.deleted_at')
            ->when($filters->search !== null, fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner
                    ->where('transactions.description', 'like', self::term($filters))
                    ->orWhere('transactions.type', 'like', self::term($filters)),
            ))
            ->select([
                DB::raw("'money' as source"),
                'transactions.id as source_id',
                'transactions.type as event_key',
                DB::raw("'system' as actor_type"),
                DB::raw('null as actor_id'),
                'transactions.reference_id as subject_id',
                'transactions.description as payload',
                'transactions.amount_cents',
                'transactions.created_at as occurred_at',
            ]);
    }

    private function messageRows(Project $project, HistoryFilters $filters): Builder
    {
        return DB::table('messages')
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->join('posts', 'posts.id', '=', 'conversations.post_id')
            ->where('posts.project_id', $project->id)
            ->whereNull('posts.deleted_at')
            ->when($filters->search !== null, fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner
                    ->where('conversations.subject', 'like', self::term($filters))
                    ->orWhere('messages.body', 'like', self::term($filters)),
            ))
            ->select([
                DB::raw("'message' as source"),
                'messages.id as source_id',
                'messages.sender_type as event_key',
                'messages.sender_type as actor_type',
                'messages.sender_id as actor_id',
                'conversations.post_id as subject_id',
                'conversations.subject as payload',
                DB::raw('null as amount_cents'),
                'messages.created_at as occurred_at',
            ]);
    }

    private static function term(HistoryFilters $filters): string
    {
        return '%'.addcslashes((string) $filters->search, '%_\\').'%';
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function postEventKeys(): array
    {
        return array_map(static fn (PostStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
        ], PostStatus::cases());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function moneyEventKeys(): array
    {
        return array_map(static fn (TransactionType $type): array => [
            'value' => $type->value,
            'label' => ucfirst($type->value),
        ], TransactionType::cases());
    }
}
