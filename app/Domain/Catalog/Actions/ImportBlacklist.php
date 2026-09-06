<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Blacklist;
use App\Domain\Catalog\Models\Website;
use App\Models\User;

/**
 * A textarea of domains, turned into blacklist rows.
 *
 * The report back is the point. An import that says "42 imported" when the
 * advertiser pasted 50 lines leaves them to work out which eight were dropped
 * and why — so this answers in three named groups, and the unmatched list comes
 * back in full rather than as a count.
 */
final class ImportBlacklist
{
    /** Enough for a spreadsheet column, small enough to stay one query. */
    public const MAX_LINES = 500;

    /**
     * @return array{blocked: list<string>, already: list<string>, unmatched: list<string>}
     */
    public function handle(User $user, string $input, ?string $reason = null): array
    {
        $domains = $this->parse($input);

        if ($domains === []) {
            return ['blocked' => [], 'already' => [], 'unmatched' => []];
        }

        // One query for the whole paste, keyed by domain so the three groups
        // fall out of set arithmetic rather than a lookup per line.
        $sites = Website::query()
            ->whereIn('domain', $domains)
            ->pluck('id', 'domain');

        $existing = Blacklist::query()
            ->where('user_id', $user->id)
            ->whereIn('website_id', $sites->values())
            ->pluck('website_id')
            ->flip();

        $blocked = [];
        $already = [];
        $unmatched = [];
        $rows = [];

        foreach ($domains as $domain) {
            $websiteId = $sites->get($domain);

            if ($websiteId === null) {
                // Not in the catalog. Reported rather than silently dropped:
                // a typo and a site Publinza does not carry look identical from
                // here, and only the advertiser can tell them apart.
                $unmatched[] = $domain;

                continue;
            }

            if ($existing->has($websiteId)) {
                $already[] = $domain;

                continue;
            }

            $blocked[] = $domain;
            $rows[] = [
                'user_id' => $user->id,
                'website_id' => $websiteId,
                'reason' => $reason,
                'blocked_by' => 'advertiser',
                'created_at' => now(),
            ];
        }

        if ($rows !== []) {
            Blacklist::query()->insert($rows);
        }

        return ['blocked' => $blocked, 'already' => $already, 'unmatched' => $unmatched];
    }

    /**
     * One domain per line, however it was pasted.
     *
     * People paste URLs, "www." prefixes, trailing slashes and the occasional
     * comma-separated row out of a spreadsheet. Rejecting those would be
     * technically correct and would make the feature useless for the input it
     * actually receives.
     *
     * @return list<string>
     */
    public function parse(string $input): array
    {
        $lines = preg_split('/[\r\n,;]+/', $input) ?: [];
        $domains = [];

        foreach ($lines as $line) {
            $value = trim($line);

            if ($value === '') {
                continue;
            }

            $value = preg_replace('#^https?://#i', '', $value) ?? $value;
            $value = preg_replace('#^www\.#i', '', $value) ?? $value;
            $value = strtolower(explode('/', $value)[0]);
            $value = trim($value);

            if ($value !== '' && ! in_array($value, $domains, true)) {
                $domains[] = $value;
            }

            if (count($domains) >= self::MAX_LINES) {
                break;
            }
        }

        return $domains;
    }
}
