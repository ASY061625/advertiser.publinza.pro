<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\System\Enums\ChangelogType;
use App\Domain\System\Models\ChangelogEntry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ChangelogSeeder extends Seeder
{
    /**
     * Title, type, whether it is a major release, and the body.
     *
     * The type is a ChangelogType case rather than a loose string, so a rename
     * of the vocabulary breaks this file at boot instead of at insert. It used
     * to be 'improvement' | 'fix' written into a `category` column, and when
     * that column became `type` this seeder kept writing the old name — which
     * no test caught, because every test builds changelog entries from a
     * factory and none of them ran the seeders. A fresh install did, on its
     * first `db:seed`.
     */
    public const ENTRIES = [
        ['Project scope follows you', ChangelogType::Improved, false, 'Pick a project in the sidebar and the catalog and posts screens stay scoped to it, so you no longer re-pick it on every screen.'],
        ['Trusted devices', ChangelogType::New, false, 'Two-factor accounts can now skip the code on a browser you have already proven, for 30 days. Signing out drops that trust.'],
        ['Metric history on every site', ChangelogType::New, true, 'Each site now keeps its previous monthly readings, so you can see whether traffic is climbing or sliding before you buy.'],
        ['Faster catalog filtering', ChangelogType::Improved, false, 'Filtering by traffic and domain rating now reads the latest metric snapshot directly, which took roughly a second off a filtered page.'],
        ['Frozen funds shown separately', ChangelogType::Fixed, false, 'The balance in the header showed committed funds as spendable. It now shows the available amount, with the frozen figure on hover.'],
    ];

    public function run(): void
    {
        foreach (self::ENTRIES as $index => [$title, $type, $major, $body]) {
            ChangelogEntry::query()->updateOrCreate(
                ['slug' => Str::slug($title)],
                [
                    'title' => $title,
                    'body' => $body,
                    'type' => $type->value,
                    'is_major' => $major,
                    'published_at' => now()->subDays($index * 6 + 1),
                ],
            );
        }
    }
}
