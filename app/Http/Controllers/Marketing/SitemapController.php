<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Domain\System\Models\BlogPost;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Generated rather than a static file, so a new blog post appears without a
 * deploy. Cached for an hour: crawlers hit this often and it is a database read
 * with no user-specific content.
 */
class SitemapController extends Controller
{
    /** Static routes, with how often each genuinely changes. */
    private const PAGES = [
        ['home', 'weekly', '1.0'],
        ['catalog', 'daily', '0.9'],
        ['how-it-works', 'monthly', '0.8'],
        ['pricing', 'monthly', '0.8'],
        ['blog.index', 'weekly', '0.7'],
        ['about', 'yearly', '0.5'],
        ['contact', 'yearly', '0.5'],
        ['terms', 'yearly', '0.3'],
        ['privacy', 'yearly', '0.3'],
        ['refunds', 'yearly', '0.3'],
    ];

    public function __invoke(): Response
    {
        $xml = Cache::remember('marketing:sitemap', now()->addHour(), function (): string {
            $urls = [];

            foreach (self::PAGES as [$route, $frequency, $priority]) {
                $urls[] = $this->url(route($route), null, $frequency, $priority);
            }

            BlogPost::query()->published()->latest('published_at')->each(function (BlogPost $post) use (&$urls): void {
                $urls[] = $this->url(
                    route('blog.show', $post->slug),
                    $post->updated_at?->toAtomString(),
                    'yearly',
                    '0.6',
                );
            });

            return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
                .implode("\n", $urls)."\n"
                .'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function url(string $location, ?string $lastModified, string $frequency, string $priority): string
    {
        return '  <url>'
            .'<loc>'.htmlspecialchars($location, ENT_XML1).'</loc>'
            .($lastModified === null ? '' : '<lastmod>'.$lastModified.'</lastmod>')
            .'<changefreq>'.$frequency.'</changefreq>'
            .'<priority>'.$priority.'</priority>'
            .'</url>';
    }
}
