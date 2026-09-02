<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Small per-surface display preferences, stored alongside the column settings
 * in `users.grid_preferences`.
 *
 * Kept here rather than in localStorage alone so a choice someone made follows
 * them to another browser. PostGridPreferences owns the posts grid's column
 * order and visibility; this owns the one-value settings that every surface
 * might want, starting with which layout a list renders in.
 */
final class GridPreferences
{
    public static function view(User $user, string $surface, string $fallback): string
    {
        $stored = $user->grid_preferences[$surface]['view'] ?? null;

        return is_string($stored) && $stored !== '' ? $stored : $fallback;
    }

    public static function setView(User $user, string $surface, string $view): void
    {
        $preferences = $user->grid_preferences ?? [];
        $preferences[$surface] = [...($preferences[$surface] ?? []), 'view' => $view];

        $user->forceFill(['grid_preferences' => $preferences])->save();
    }
}
