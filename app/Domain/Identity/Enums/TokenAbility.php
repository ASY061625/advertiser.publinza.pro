<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

/**
 * What a personal access token is allowed to do.
 *
 * Deliberately coarse and deliberately read-heavy. A token is a credential
 * somebody pastes into a script, and the blast radius of a leaked one is
 * whatever this list allows — so there is no "write billing" and no delete.
 * Spending money stays behind a session and a password.
 */
enum TokenAbility: string
{
    case ReadPosts = 'posts:read';
    case WritePosts = 'posts:write';
    case ReadCatalog = 'catalog:read';
    case ReadBilling = 'billing:read';

    public function label(): string
    {
        return match ($this) {
            self::ReadPosts => 'Read posts',
            self::WritePosts => 'Write posts',
            self::ReadCatalog => 'Read catalog',
            self::ReadBilling => 'Read billing',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ReadPosts => 'List posts and read their status, article and history.',
            self::WritePosts => 'Order placements and submit articles. Spends balance.',
            self::ReadCatalog => 'Search the catalog and read site metrics.',
            self::ReadBilling => 'Read the balance, the ledger and invoices. Cannot move money.',
        };
    }

    /** The one ability that can cost money, flagged in the picker. */
    public function isDestructive(): bool
    {
        return $this === self::WritePosts;
    }
}
