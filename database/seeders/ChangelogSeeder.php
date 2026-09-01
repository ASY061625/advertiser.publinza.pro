<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\System\Models\ChangelogEntry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ChangelogSeeder extends Seeder
{
    /** category => one of new | improvement | fix, which the chips key off. */
    public const ENTRIES = [
        ['Project scope follows you', 'improvement', 'Pick a project in the sidebar and the catalog and posts screens stay scoped to it, so you no longer re-pick it on every screen.'],
        ['Trusted devices', 'new', 'Two-factor accounts can now skip the code on a browser you have already proven, for 30 days. Signing out drops that trust.'],
        ['Metric history on every site', 'new', 'Each site now keeps its previous monthly readings, so you can see whether traffic is climbing or sliding before you buy.'],
        ['Faster catalog filtering', 'improvement', 'Filtering by traffic and domain rating now reads the latest metric snapshot directly, which took roughly a second off a filtered page.'],
        ['Frozen funds shown separately', 'fix', 'The balance in the header showed committed funds as spendable. It now shows the available amount, with the frozen figure on hover.'],
    ];

    public function run(): void
    {
        foreach (self::ENTRIES as $index => [$title, $category, $body]) {
            ChangelogEntry::query()->updateOrCreate(
                ['slug' => Str::slug($title)],
                [
                    'title' => $title,
                    'body' => $body,
                    'category' => $category,
                    'published_at' => now()->subDays($index * 6 + 1),
                ],
            );
        }
    }
}
