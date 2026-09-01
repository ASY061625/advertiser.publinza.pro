<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\System\Models\BlogPost;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    /** Real articles, not placeholders — the blog is indexed. */
    public const POSTS = [
        [
            'title' => 'Why we stopped reselling other people\'s sites',
            'slug' => 'why-we-stopped-reselling',
            'author' => 'Dara Nolan',
            'minutes' => 6,
            'excerpt' => 'We spent a year as a broker. Here is what kept breaking, and why owning the network was the only fix we could find.',
            'body' => <<<'HTML'
                <p>For most of 2021 we operated the way almost everyone in this industry operates. An advertiser asked for a placement, we found a publisher who had one, we took a margin, and we passed the brief along. On paper it works. In practice, three things kept going wrong, and none of them were fixable from where we sat.</p>
                <h2>The window was never ours to promise</h2>
                <p>When a site says it publishes within five days, that is the publisher's estimate of their own calendar. As a broker you repeat it, because it is all you have. When the publisher is busy, or on holiday, or has simply stopped reading email, you have nothing to offer the advertiser except an apology and a chase. We were quoting windows we could not control, which meant we were quoting guesses.</p>
                <h2>The metrics were secondhand</h2>
                <p>Publishers send you a screenshot. Sometimes it is current. Sometimes it is from the month the site peaked. You can re-measure it yourself, and we did, but you are then telling a publisher their own numbers are wrong, which is an awkward conversation to have with someone whose cooperation you need.</p>
                <h2>Nobody owned a removed link</h2>
                <p>This was the one that decided it. A link placed in March disappears in September because the publisher redesigned, or sold the site, or quietly cleaned up their sponsored posts. The advertiser paid us. We paid the publisher. The publisher has no contractual reason to care and often no longer replies. We would refund out of our own margin, which is the right thing to do and also unsustainable.</p>
                <h2>What changed</h2>
                <p>We started buying sites instead. Slowly, because a site worth buying takes months to find and diligence. Every site in the catalog today is one we own, with an editor we employ and a publishing calendar we set. That makes the window a commitment, the metrics ours to measure, and a removed link our problem to fix.</p>
                <p>The trade-off is real: our catalog is smaller than a marketplace's, and it will stay that way. If you need a placement on one specific site and we do not own it, we cannot help. We would rather say that than source it from a broker and inherit all three problems again.</p>
                HTML,
        ],
        [
            'title' => 'How we measure the sites in our network',
            'slug' => 'how-we-measure-sites',
            'author' => 'Ruth Kelleher',
            'minutes' => 5,
            'excerpt' => 'Traffic, DR, DA and spam score, where each number comes from, how often we refresh it, and which ones we think are worth anything.',
            'body' => <<<'HTML'
                <p>Every site in the catalog carries four numbers: monthly traffic, Ahrefs domain rating, Moz domain authority, and a spam score. They are refreshed on the first working day of each month, for the whole network at once, and the previous readings stay on record.</p>
                <h2>Monthly organic traffic</h2>
                <p>This is estimated organic search traffic, not sessions from our own analytics. We use the estimate deliberately: it is the same basis your other suppliers quote, so you can compare like with like. Our analytics numbers are usually higher, and quoting those would flatter us.</p>
                <h2>Domain rating and domain authority</h2>
                <p>DR is Ahrefs, DA is Moz. They measure similar things by different methods and they disagree, sometimes by fifteen points. We show both rather than picking whichever is higher for a given site.</p>
                <h2>Spam score</h2>
                <p>Lower is better, which is why the bar next to it fills from the other end in the catalog. Anything above roughly 30 gets reviewed internally, and sites that stay there get sold or shut rather than listed.</p>
                <h2>Why the history matters</h2>
                <p>A single reading tells you where a site is. Three readings tell you where it is going. A site at DR 62 and falling is a different proposition from one at DR 58 and climbing, and the catalog shows you which is which. We keep the history visible because hiding it would only help us on the sites we should be least proud of.</p>
                HTML,
        ],
        [
            'title' => 'What a fair guest post price actually covers',
            'slug' => 'what-a-guest-post-price-covers',
            'author' => 'Dara Nolan',
            'minutes' => 4,
            'excerpt' => 'A breakdown of where the money goes on a placement, and why the same link costs $95 on one site and $1,240 on another.',
            'body' => <<<'HTML'
                <p>Placement prices in this industry look arbitrary because the thing being sold is rarely itemised. Here is ours, for an article placement.</p>
                <h2>The site's editorial slot</h2>
                <p>Most of the price. A site that gets 400,000 visits a month has a limited number of posts it can publish without diluting itself, and each one carries an opportunity cost against its own editorial plan. A site at 3,000 visits has neither constraint, which is why the same work costs a fraction as much there.</p>
                <h2>Writing, if you want it</h2>
                <p>A separate line, between $30 and $90 depending on length and subject. If you supply the article, you do not pay it. We would rather show it as its own charge than bundle it and let you wonder whether you are paying twice for something you did yourself.</p>
                <h2>The 12-month commitment</h2>
                <p>Keeping a link live for a year, and replacing or refunding it if it comes down, is a cost we carry rather than a promise we make for free. It is priced into the placement.</p>
                <h2>What is not in there</h2>
                <p>There is no publisher margin, because there is no publisher. That is the single biggest difference between our prices and a broker's for a comparable site, and it is worth checking: take a site of similar traffic and DR elsewhere and compare.</p>
                HTML,
        ],
        [
            'title' => 'Anchor text: what we will and will not publish',
            'slug' => 'anchor-text-policy',
            'author' => 'Ruth Kelleher',
            'minutes' => 4,
            'excerpt' => 'Our editors reject a small share of briefs. These are the reasons, so you can write one that goes through first time.',
            'body' => <<<'HTML'
                <p>Roughly one brief in twelve comes back from our editors for a change. Almost all of them fall into four groups.</p>
                <h2>Exact-match anchors used repeatedly</h2>
                <p>One exact-match commercial anchor in a 900-word article reads as editorial. Three read as an advert, and our editors will ask for two of them to become brand or partial matches. This is a judgement about the site's credibility, which is the asset you are buying.</p>
                <h2>Claims we cannot support</h2>
                <p>"The best CRM in Europe" needs either a source or a rewrite. Our editors are not fact-checking your product, but they will not publish a superlative on a site we own without something behind it.</p>
                <h2>Regulated subjects on the wrong site</h2>
                <p>Some of our sites accept gambling, crypto or CBD content, and each one lists what it takes. A brief on a subject a site does not accept is redirected rather than rejected: we will suggest sites in the network that do.</p>
                <h2>Links to a page that redirects</h2>
                <p>If the target URL 301s somewhere else, we ask for the destination instead. A link that passes through a redirect is worth less to you, and it makes the article look stale a year from now.</p>
                <p>You can avoid most of this by writing the project brief once, properly, and letting every placement inherit it.</p>
                HTML,
        ],
    ];

    public function run(): void
    {
        foreach (self::POSTS as $index => $post) {
            BlogPost::query()->updateOrCreate(
                ['slug' => $post['slug']],
                [
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'body_html' => $post['body'],
                    'author' => $post['author'],
                    'reading_minutes' => $post['minutes'],
                    'published_at' => now()->subWeeks($index * 3 + 1),
                ],
            );
        }
    }
}
