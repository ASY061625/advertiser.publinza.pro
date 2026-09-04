<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Catalog\Models\Favorite;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\System\Models\ChangelogEntry;
use App\Domain\Trading\Models\Cart;
use App\Domain\Trading\Models\CartItem;
use App\Domain\Trading\Support\CartPricer;
use App\Models\User;
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

    public function __construct(private readonly CartPricer $pricer) {}

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
                    // Google's favicon service, not a stored asset: the sites
                    // are ours but their icons change without telling us.
                    'favicon' => $domain === null
                        ? null
                        : "https://www.google.com/s2/favicons?sz=32&domain={$domain}",
                    'excerpt' => $latest === null ? '' : Str::limit(strip_tags($latest->body), 80),
                    'at' => $conversation->last_message_at?->toIso8601String(),
                    'unread' => $conversation->unread_count > 0,
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
        return ChangelogEntry::query()
            ->published()
            ->when(
                $user->changelog_read_at !== null,
                fn ($q) => $q->where('published_at', '>', $user->changelog_read_at),
            )
            ->count();
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
