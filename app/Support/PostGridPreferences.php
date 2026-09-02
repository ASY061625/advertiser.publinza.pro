<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Column order and visibility for the /posts grid.
 *
 * The canonical list lives here, in one place, so the server and the client
 * cannot disagree about which columns exist. A stored preference is filtered
 * against it on the way out: a column removed from the product must not linger
 * in someone's saved order, and a column added later has to appear rather than
 * being invisible to everyone who ever touched their settings.
 */
final class PostGridPreferences
{
    public const SURFACE = 'posts';

    /** id => [label, whether it can be hidden] */
    public const COLUMNS = [
        'id' => ['Post ID', true],
        'website' => ['Website', false],
        'project' => ['Project', true],
        'folder' => ['Folder', true],
        'anchor_text' => ['Anchor text', true],
        'target_url' => ['Target URL', true],
        'status' => ['Status', false],
        'price' => ['Price', true],
        'created_at' => ['Created', true],
        'published_at' => ['Published', true],
        'deadline_at' => ['Deadline', true],
    ];

    /** Shown by default. The rest start hidden to keep the first view readable. */
    public const DEFAULT_HIDDEN = ['folder', 'target_url'];

    /**
     * @return array{order: list<string>, hidden: list<string>, available: list<array{id: string, label: string, lockable: bool}>}
     */
    public static function for(User $user): array
    {
        $stored = $user->grid_preferences[self::SURFACE] ?? [];
        $ids = array_keys(self::COLUMNS);

        $order = array_values(array_intersect(
            array_map('strval', (array) ($stored['order'] ?? [])),
            $ids,
        ));

        // A column the stored order does not mention is one that was added to
        // the product after this preference was saved. It goes on the end, in
        // canonical order: the arrangement someone chose stays exactly as they
        // left it, and the new column is visible for them to place themselves.
        foreach ($ids as $id) {
            if (! in_array($id, $order, true)) {
                $order[] = $id;
            }
        }

        $hidden = isset($stored['hidden'])
            ? array_values(array_intersect(array_map('strval', (array) $stored['hidden']), $ids))
            : self::DEFAULT_HIDDEN;

        // A column the grid cannot function without is never hidden, whatever
        // an old preference or a hand-edited request says.
        $hidden = array_values(array_filter(
            $hidden,
            static fn (string $id): bool => self::COLUMNS[$id][1] === true,
        ));

        return [
            'order' => $order,
            'hidden' => $hidden,
            'available' => array_map(static fn (string $id): array => [
                'id' => $id,
                'label' => self::COLUMNS[$id][0],
                'lockable' => self::COLUMNS[$id][1] === false,
            ], $ids),
        ];
    }

    /**
     * @param  list<string>  $order
     * @param  list<string>  $hidden
     */
    public static function store(User $user, array $order, array $hidden): void
    {
        $ids = array_keys(self::COLUMNS);

        $preferences = $user->grid_preferences ?? [];
        $preferences[self::SURFACE] = [
            'order' => array_values(array_intersect($order, $ids)),
            'hidden' => array_values(array_filter(
                array_intersect($hidden, $ids),
                static fn (string $id): bool => self::COLUMNS[$id][1] === true,
            )),
        ];

        $user->forceFill(['grid_preferences' => $preferences])->save();
    }
}
