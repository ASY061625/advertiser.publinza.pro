<?php

declare(strict_types=1);

namespace App\Domain\Intelligence\Support;

use App\Domain\Intelligence\Contracts\MetricsProvider;
use App\Domain\Intelligence\Providers\AhrefsProvider;
use App\Domain\Intelligence\Providers\DataForSeoProvider;
use App\Domain\Intelligence\Providers\MozProvider;
use App\Domain\Intelligence\Providers\SampleMetricsProvider;
use App\Domain\Intelligence\Providers\SemrushProvider;
use Illuminate\Support\Facades\Log;

/**
 * Which vendor answers, and what to call the ones that answered before.
 *
 * `current()` is config plus one guard: a provider whose credentials are not
 * set cannot be the current one, because selecting it would queue a fetch that
 * every row fails on with an authentication error nobody can act on from the
 * tab. The fallback is the sample provider, which says so on every row.
 *
 * `labelFor()` exists separately because a stored row keeps the key of whoever
 * produced it, and that may no longer be the configured vendor — the tab has to
 * name the source of the numbers it is showing, not the source of the next ones.
 */
final class MetricsProviderRegistry
{
    /** @var array<string, class-string<MetricsProvider>> */
    private const PROVIDERS = [
        'ahrefs' => AhrefsProvider::class,
        'semrush' => SemrushProvider::class,
        'moz' => MozProvider::class,
        'dataforseo' => DataForSeoProvider::class,
        'sample' => SampleMetricsProvider::class,
    ];

    public function current(): MetricsProvider
    {
        $configured = (string) config('publinza.competitors.provider', 'sample');
        $provider = $this->make($configured);

        if ($provider === null) {
            Log::warning('Unknown competitor metrics provider configured.', ['provider' => $configured]);

            return new SampleMetricsProvider;
        }

        if (! $provider->isConfigured()) {
            // Once, at resolve time, rather than once per queued row.
            Log::warning('Competitor metrics provider has no credentials; using sample data.', [
                'provider' => $provider->key(),
            ]);

            return new SampleMetricsProvider;
        }

        return $provider;
    }

    /** How a stored row's provider is named to an advertiser. */
    public function labelFor(?string $key): string
    {
        return $this->make((string) $key)?->label() ?? 'an unknown source';
    }

    private function make(string $key): ?MetricsProvider
    {
        $class = self::PROVIDERS[$key] ?? null;

        return $class === null ? null : app($class);
    }
}
