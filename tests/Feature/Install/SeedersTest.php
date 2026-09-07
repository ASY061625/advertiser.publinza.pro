<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Website;
use App\Domain\System\Enums\ChangelogType;
use App\Domain\System\Models\ChangelogEntry;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

/**
 * The seeders, run for real.
 *
 * Every other test in this suite builds its own rows from factories, so nothing
 * exercised `php artisan db:seed` — and a column rename left ChangelogSeeder
 * writing to a column that no longer existed. The suite stayed green; a fresh
 * installation died on the first seed. This is the test that would have caught
 * it, and it costs one run of the seeders.
 */
it('seeds a fresh database without throwing', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(Website::query()->count())->toBeGreaterThan(0)
        ->and(User::query()->count())->toBeGreaterThan(0)
        ->and(ChangelogEntry::query()->count())->toBeGreaterThan(0);
});

it('seeds changelog entries the current schema can read back', function (): void {
    $this->seed(DatabaseSeeder::class);

    $entries = ChangelogEntry::query()->get();

    // Casting is where a stale seeder shows up: a row written with the old
    // vocabulary throws on read rather than at insert.
    foreach ($entries as $entry) {
        expect($entry->type)->toBeInstanceOf(ChangelogType::class)
            ->and($entry->is_major)->toBeBool();
    }

    // The one-time announcement modal needs something to announce, and a demo
    // install with no major entry never shows that surface at all.
    expect($entries->where('is_major', true))->not->toBeEmpty();
});

it('is idempotent, because a deploy runs it again', function (): void {
    $this->seed(DatabaseSeeder::class);
    $first = ChangelogEntry::query()->count();

    $this->seed(DatabaseSeeder::class);

    expect(ChangelogEntry::query()->count())->toBe($first);
});
