<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Catalog\Models\Favorite;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Notifications\Support\NotificationCentre;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\System\Models\ChangelogEntry;
use App\Domain\System\Support\ChangelogHtml;
use App\Domain\Trading\Models\Cart;
use App\Domain\Trading\Models\CartItem;
use App\Domain\Trading\Support\CartPricer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Everything the persistent app shell needs, on every authenticated request.
 *
 * This runs on each page load, so every query here is either an aggregate or a
 * small capped list. The dropdown previews are included rather than fetched on
 * open: five conversations and five cart lines cost less than the round trip
 * would, and the menus open instantly.
 */
final class ShellData
{
    /** Deterministic project dots. Same project, same colour, every session. */
    private const PROJECT_COLOURS = [
        '#1D4ED8', '#14B8A6', '#F59E0B', '#7E22CE',
        '#0F9D74', '#DC2626', '#2B3A52', '#0EA5E9',
    ];

    private const PREVIEW_LIMIT = 5;

    public function __construct(
        private readonly CartPricer $pricer,
        private readonly NotificationCentre $centre,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        // The prices come with it: the preview's subtotal has to be the live
        // one, or the header quietly disagrees with the cart page about what
        // the same lines cost.
        $cart = Cart::query()
            ->with(['items.website:id,domain', 'items.website.prices', 'items.project:id,name'])
            ->firstWhere('user_id', $user->id);

        $wallet = $user->wallet;

        return [
            'version' => config('publinza.version'),
            'sidebarCollapsed' => (bool) $user->sidebar_collapsed,
            'projects' => $this->projects($user),
            'balance' => [
                'availableCents' => $wallet?->available_cents ?? 0,
                'frozenCents' => $wallet?->frozen_cents ?? 0,
            ],
            'cart' => $this->cart($cart),
            'conversations' => $this->conversations($user),
            'counts' => [
                'cart' => $cart?->items->count() ?? 0,
                'conversations' => $this->unreadConversations($user),
                'changelog' => $this->unreadChangelog($user),
                // Split from the count above because the header renders them
                // differently: a dot for anything unseen, a number only when
                // one of the unseen ones is a major release.
                'changelogMajor' => $this->unseenMajorChangelog($user),
                'notifications' => $this->centre->unreadCount($user),
                'favorites' => Favorite::query()->where('user_id', $user->id)->count(),
            ],
            // Null unless a broadcaster is configured. The shell falls back to
            // a 60-second poll, which is a working state, not a degraded one.
            'echo' => $this->echoConfig(),
        ];
    }

    /**
     * @return list<array{id: int, name: string, color: string}>
     */
    private function projects(User $user): array
    {
        return Project::query()
            ->where('user_id', $user->id)
            ->where('status', ProjectStatus::Active)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'color' => self::PROJECT_COLOURS[$project->id % count(self::PROJECT_COLOURS)],
            ])
            ->all();
    }

    /**
     * @return array{items: list<array<string, mixed>>, subtotalCents: int, moreCount: int}
     */
    private function cart(?Cart $cart): array
    {
        if ($cart === null) {
            return ['items' => [], 'subtotalCents' => 0, 'moreCount' => 0];
        }

        $items = $cart->items->take(self::PREVIEW_LIMIT)->map(fn (CartItem $item): array => [
            'id' => $item->id,
            'domain' => $item->website?->domain ?? '',
            'project' => $item->project?->name,
            // The live price with its fees, from the same place the cart page
            // and the order read it. The snapshot on the line is what it was
            // quoted, not what it costs — see CartPricer.
            'priceCents' => $this->pricer->total($item)->cents,
        ])->values()->all();

        return [
            'items' => $items,
            // The subtotal is the whole cart, not just the preview — a total
            // that only counts five lines would be worse than no total.
            'subtotalCents' => $this->pricer->sum($cart->items)->cents,
            'moreCount' => max(0, $cart->items->count() - self::PREVIEW_LIMIT),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function conversations(User $user): array
    {
        return Conversation::query()
            ->where('user_id', $user->id)
            ->with(['website:id,domain'])
            ->withCount(['messages as unread_count' => fn ($q) => $q
                ->whereNull('read_at')
                ->where('sender_type', '!=', 'user')])
            ->with(['messages' => fn ($q) => $q->latest('created_at')->limit(1)])
            ->orderByDesc('last_message_at')
            ->take(self::PREVIEW_LIMIT)
            ->get()
            ->map(function (Conversation $conversation): array {
                $latest = $conversation->messages->first();
                $domain = $conversation->website?->domain;

                return [
                    'id' => $conversation->id,
                    'domain' => $domain ?? $conversation->subject,
                    /*
                     * Always null, and deliberately so.
                     *
                     * This used to point at Google's favicon service, which
                     * ships every domain the advertiser is buying on to a third
                     * party on each page load — the same trade the posts grid
                     * refuses to make, for the same reason. The menu falls back
                     * to a glyph of the same size, so nothing shifts if
                     * Publinza ever stores its own site marks.
                     */
                    'favicon' => null,
                    'excerpt' => $latest === null ? '' : Str::limit(strip_tags($latest->body), 80),
                    'at' => $conversation->last_message_at?->toIso8601String(),
                    // A withCount alias, not a column — read as an attribute
                    // so it does not look like a property the model declares.
                    'unread' => (int) $conversation->getAttribute('unread_count') > 0,
                ];
            })
            ->all();
    }

    private function unreadConversations(User $user): int
    {
        return Conversation::query()
            ->where('user_id', $user->id)
            ->whereHas('messages', fn ($q) => $q
                ->whereNull('read_at')
                ->where('sender_type', '!=', 'user'))
            ->count();
    }

    private function unreadChangelog(User $user): int
    {
        return $this->unseenChangelog($user)->count();
    }

    private function unseenMajorChangelog(User $user): int
    {
        return $this->unseenChangelog($user)->where('is_major', true)->count();
    }

    /**
     * @return Builder<ChangelogEntry>
     */
    private function unseenChangelog(User $user): Builder
    {
        return ChangelogEntry::query()
            ->published()
            ->when(
                $user->last_seen_changelog_at !== null,
                fn ($q) => $q->where('published_at', '>', $user->last_seen_changelog_at),
            );
    }

    /**
     * The one major entry that still owes somebody a modal, if there is one.
     *
     * Keyed on changelog_major_ack_at rather than last_seen_changelog_at: the
     * drawer clears the dot for everything, and it must not also dismiss an
     * announcement nobody has actually seen.
     *
     * @return array<string, mixed>|null
     */
    public function majorAnnouncement(User $user): ?array
    {
        $entry = ChangelogEntry::query()
            ->published()
            ->where('is_major', true)
            ->when(
                $user->changelog_major_ack_at !== null,
                fn ($q) => $q->where('published_at', '>', $user->changelog_major_ack_at),
            )
            ->latest('published_at')
            ->first();

        if ($entry === null) {
            return null;
        }

        return [
            'id' => $entry->id,
            'title' => $entry->title,
            'body' => ChangelogHtml::clean($entry->body),
            'type' => $entry->type->value,
            'typeLabel' => $entry->type->label(),
            'imageUrl' => $entry->imageUrl(),
            'publishedAt' => $entry->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function echoConfig(): ?array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return null;
        }

        return [
            'key' => config('broadcasting.connections.reverb.key'),
            'host' => config('broadcasting.connections.reverb.options.host'),
            'port' => config('broadcasting.connections.reverb.options.port'),
            'scheme' => config('broadcasting.connections.reverb.options.scheme'),
        ];
    }
}
