<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Catalog\Enums\LinkType;
use App\Domain\Catalog\Enums\MetricSource;
use App\Domain\Catalog\Models\Country;
use App\Domain\Catalog\Models\Language;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteCategory;
use App\Domain\Trading\Enums\ServiceType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 60 websites across the 14 categories, 8 languages and a spread of countries.
 *
 * Metrics and prices are derived from a tier rather than drawn independently,
 * because independent randoms produce nonsense — a DR 89 site priced at $60
 * next to a DR 20 site at $2,000 makes the catalog's quant-bars meaningless and
 * hides real filtering bugs behind implausible data.
 */
class WebsiteSeeder extends Seeder
{
    /**
     * [domain, title, category slug, language, country, tier]
     *
     * @var list<array{string, string, string, string, string, string}>
     */
    public const SITES = [
        // --- Technology -----------------------------------------------------
        ['stackpulse.io', 'StackPulse — engineering deep dives', 'technology', 'en', 'US', 'premium'],
        ['devnotes.dev', 'DevNotes', 'technology', 'en', 'GB', 'strong'],
        ['technikwelt.de', 'Technikwelt', 'technology', 'de', 'DE', 'strong'],
        ['codigoabierto.es', 'Código Abierto', 'technology', 'es', 'ES', 'mid'],
        ['gadgetlab.nl', 'GadgetLab', 'technology', 'nl', 'NL', 'mid'],
        ['tinkerbyte.dev', 'Tinkerbyte', 'technology', 'en', 'CA', 'longtail'],
        // --- Finance --------------------------------------------------------
        ['ledgerwise.com', 'Ledgerwise', 'finance', 'en', 'US', 'premium'],
        ['finanzblick.de', 'Finanzblick', 'finance', 'de', 'DE', 'strong'],
        ['moneymatters.co.uk', 'Money Matters', 'finance', 'en', 'GB', 'strong'],
        ['patrimoine-net.fr', 'Patrimoine Net', 'finance', 'fr', 'FR', 'mid'],
        ['investire-oggi.it', 'Investire Oggi', 'finance', 'it', 'IT', 'mid'],
        ['budgetkompas.se', 'Budgetkompas', 'finance', 'en', 'SE', 'longtail'],
        // --- Health & Fitness ----------------------------------------------
        ['vitalclinic.com', 'Vital Clinic', 'health-fitness', 'en', 'US', 'premium'],
        ['runstronger.co', 'Run Stronger', 'health-fitness', 'en', 'AU', 'strong'],
        ['gesundleben.at', 'Gesund Leben', 'health-fitness', 'de', 'AT', 'mid'],
        ['saludplena.es', 'Salud Plena', 'health-fitness', 'es', 'MX', 'mid'],
        ['bienetreactif.fr', 'Bien-être Actif', 'health-fitness', 'fr', 'FR', 'longtail'],
        // --- Marketing & SEO ------------------------------------------------
        ['growthdispatch.com', 'Growth Dispatch', 'marketing-seo', 'en', 'US', 'premium'],
        ['searchmetrics.blog', 'The Search Blog', 'marketing-seo', 'en', 'GB', 'strong'],
        ['marketingdigital.pt', 'Marketing Digital', 'marketing-seo', 'pt', 'PT', 'mid'],
        ['linkbuildly.com', 'Linkbuildly', 'marketing-seo', 'en', 'IE', 'mid'],
        ['ranktactics.io', 'Rank Tactics', 'marketing-seo', 'en', 'SG', 'longtail'],
        // --- Business -------------------------------------------------------
        ['foundersweekly.com', 'Founders Weekly', 'business', 'en', 'US', 'premium'],
        ['bizjournal.co.uk', 'Business Journal', 'business', 'en', 'GB', 'strong'],
        ['unternehmerheute.de', 'Unternehmer Heute', 'business', 'de', 'DE', 'mid'],
        ['negocios-hoy.es', 'Negocios Hoy', 'business', 'es', 'AR', 'mid'],
        ['scaleupdiary.com', 'Scaleup Diary', 'business', 'en', 'NZ', 'longtail'],
        // --- Travel ---------------------------------------------------------
        ['wanderfar.com', 'Wanderfar', 'travel', 'en', 'US', 'premium'],
        ['reiselust.de', 'Reiselust', 'travel', 'de', 'DE', 'strong'],
        ['voyageursolo.fr', 'Voyageur Solo', 'travel', 'fr', 'FR', 'mid'],
        ['viagginauta.it', 'Viaggi Nauta', 'travel', 'it', 'IT', 'mid'],
        ['mochilaviajes.es', 'Mochila Viajes', 'travel', 'es', 'ES', 'longtail'],
        // --- Home & Garden --------------------------------------------------
        ['homecraftly.com', 'Homecraftly', 'home-garden', 'en', 'US', 'strong'],
        ['gartenfreude.de', 'Gartenfreude', 'home-garden', 'de', 'DE', 'mid'],
        ['casaejardim.pt', 'Casa e Jardim', 'home-garden', 'pt', 'BR', 'mid'],
        ['tuinleven.nl', 'Tuinleven', 'home-garden', 'nl', 'NL', 'longtail'],
        // --- Fashion & Beauty -----------------------------------------------
        ['styleatlas.com', 'Style Atlas', 'fashion-beauty', 'en', 'US', 'premium'],
        ['modaviva.it', 'Moda Viva', 'fashion-beauty', 'it', 'IT', 'strong'],
        ['beautelab.fr', 'Beauté Lab', 'fashion-beauty', 'fr', 'FR', 'mid'],
        ['glansmagazine.nl', 'Glans Magazine', 'fashion-beauty', 'nl', 'BE', 'longtail'],
        // --- Food & Drink ---------------------------------------------------
        ['tastelines.com', 'Tastelines', 'food-drink', 'en', 'US', 'strong'],
        ['cucinareoggi.it', 'Cucinare Oggi', 'food-drink', 'it', 'IT', 'strong'],
        ['recetasreales.es', 'Recetas Reales', 'food-drink', 'es', 'ES', 'mid'],
        ['kuchniapolska.pl', 'Kuchnia Polska', 'food-drink', 'pl', 'PL', 'mid'],
        ['brewnotes.co.uk', 'Brew Notes', 'food-drink', 'en', 'GB', 'longtail'],
        // --- Automotive -----------------------------------------------------
        ['torqueweekly.com', 'Torque Weekly', 'automotive', 'en', 'US', 'strong'],
        ['autowelt.de', 'Autowelt', 'automotive', 'de', 'DE', 'strong'],
        ['motorpasion.es', 'Motor Pasión', 'automotive', 'es', 'ES', 'mid'],
        // --- Education ------------------------------------------------------
        ['learnwell.org', 'Learnwell', 'education', 'en', 'US', 'strong'],
        ['studienguide.de', 'Studienguide', 'education', 'de', 'DE', 'mid'],
        ['aprenderhoy.es', 'Aprender Hoy', 'education', 'es', 'MX', 'mid'],
        ['skillpathindia.in', 'SkillPath India', 'education', 'en', 'IN', 'longtail'],
        // --- Real Estate ----------------------------------------------------
        ['propertylens.com', 'Property Lens', 'real-estate', 'en', 'US', 'strong'],
        ['immobilienrat.de', 'Immobilienrat', 'real-estate', 'de', 'DE', 'mid'],
        ['imoveisbrasil.pt', 'Imóveis Brasil', 'real-estate', 'pt', 'BR', 'longtail'],
        // --- Gaming ---------------------------------------------------------
        ['pixelfront.gg', 'Pixelfront', 'gaming', 'en', 'US', 'premium'],
        ['spielraum.de', 'Spielraum', 'gaming', 'de', 'DE', 'mid'],
        ['graczpl.pl', 'Gracz PL', 'gaming', 'pl', 'PL', 'longtail'],
        // --- Legal ----------------------------------------------------------
        ['lawbrief.co.uk', 'Law Brief', 'legal', 'en', 'GB', 'strong'],
        ['rechtsblick.de', 'Rechtsblick', 'legal', 'de', 'DE', 'mid'],
    ];

    /**
     * Tier → [drMin, drMax, trafficMin, trafficMax, priceMin, priceMax] in cents.
     */
    private const TIERS = [
        'premium' => [76, 92, 300_000, 1_900_000, 65_000, 250_000],
        'strong' => [55, 75, 60_000, 300_000, 25_000, 70_000],
        'mid' => [35, 54, 10_000, 60_000, 11_000, 30_000],
        'longtail' => [15, 34, 800, 12_000, 4_500, 13_000],
    ];

    public function run(): void
    {
        // Reproducible: the same seed run twice gives the same catalog, so a
        // screenshot or a failing test can be reproduced later.
        fake()->seed(20260901);

        $categories = WebsiteCategory::query()->pluck('id', 'slug');
        $languages = Language::query()->pluck('id', 'code');
        $countries = Country::query()->pluck('id', 'code');
        $topics = ['gambling', 'cbd-cannabis', 'cryptocurrency', 'forex-trading', 'alcohol', 'vaping-tobacco'];

        foreach (self::SITES as [$domain, $title, $categorySlug, $languageCode, $countryCode, $tier]) {
            [$drMin, $drMax, $trafficMin, $trafficMax, $priceMin, $priceMax] = self::TIERS[$tier];

            $dr = fake()->numberBetween($drMin, $drMax);
            // Traffic tracks DR within the tier, with enough jitter that the
            // catalog's quant-bars are not a straight line.
            $position = ($dr - $drMin) / max(1, $drMax - $drMin);
            $traffic = (int) ($trafficMin + $position * ($trafficMax - $trafficMin));
            $traffic = (int) ($traffic * fake()->randomFloat(2, 0.7, 1.35));

            $priceCents = (int) round(($priceMin + $position * ($priceMax - $priceMin)) / 100) * 100;
            $priceCents = max(1_000, (int) round($priceCents * fake()->randomFloat(2, 0.85, 1.2) / 100) * 100);

            // Stronger sites are cleaner and take longer to publish.
            $spamScore = max(0, (int) round((100 - $dr) / 8) + fake()->numberBetween(-1, 3));

            $website = Website::query()->updateOrCreate(
                ['domain' => $domain],
                [
                    'slug' => Str::slug($domain),
                    'title' => $title,
                    'description' => "{$title} publishes original editorial for a "
                        .strtolower(str_replace('-', ' ', $categorySlug)).' audience.',
                    'category_id' => $categories[$categorySlug],
                    'primary_language_id' => $languages[$languageCode],
                    'country_id' => $countries[$countryCode],
                    'is_active' => true,
                    'is_featured' => $tier === 'premium' && fake()->boolean(50),
                    'accepts_sensitive_topics' => fake()->boolean(35)
                        ? fake()->randomElements($topics, fake()->numberBetween(1, 3))
                        : [],
                    'publication_period_hours' => match ($tier) {
                        'premium' => fake()->randomElement([120, 168]),
                        'strong' => fake()->randomElement([72, 120]),
                        default => fake()->randomElement([24, 48, 72]),
                    },
                    'link_type' => fake()->boolean(78) ? LinkType::Dofollow : LinkType::Nofollow,
                    'links_allowed' => fake()->numberBetween(1, 3),
                    'max_links' => fake()->numberBetween(2, 4),
                    'min_words' => fake()->randomElement([600, 800, 1000, 1200, 1500]),
                    'sample_url' => "https://{$domain}/sample-guest-post",
                    'guidelines' => 'Original copy only. No affiliate links in the first paragraph. '
                        .'One follow link to the advertiser, additional links at editorial discretion.',
                ],
            );

            $this->seedPrices($website, $priceCents);
            $this->seedMetrics($website, $dr, $traffic, $spamScore);
        }
    }

    private function seedPrices(Website $website, int $priceCents): void
    {
        $services = [
            [ServiceType::ArticlePlacement, $priceCents],
            // Link insertion is cheaper: no article to produce.
            [ServiceType::LinkInsertion, (int) round($priceCents * 0.6 / 100) * 100],
        ];

        if (fake()->boolean(35)) {
            $services[] = [ServiceType::Homepage, (int) round($priceCents * 2.4 / 100) * 100];
        }

        if (fake()->boolean(20)) {
            $services[] = [ServiceType::Banner, (int) round($priceCents * 1.5 / 100) * 100];
        }

        foreach ($services as [$service, $cents]) {
            $website->prices()->updateOrCreate(
                ['service_type' => $service],
                [
                    'price_cents' => $cents,
                    'writing_fee_cents' => fake()->randomElement([3_000, 4_500, 6_000, 9_000]),
                    'express_fee_cents' => (int) round($cents * 0.25 / 100) * 100,
                ],
            );
        }
    }

    /** Three monthly snapshots, so the metric history is a trend and not a point. */
    private function seedMetrics(Website $website, int $dr, int $traffic, int $spamScore): void
    {
        for ($monthsAgo = 2; $monthsAgo >= 0; $monthsAgo--) {
            $drift = 1 - ($monthsAgo * fake()->randomFloat(3, 0.01, 0.06));

            $website->metrics()->updateOrCreate(
                ['fetched_at' => now()->subMonths($monthsAgo)->startOfDay()],
                [
                    'monthly_traffic' => (int) ($traffic * $drift),
                    'ahrefs_dr' => max(1, $dr - $monthsAgo),
                    'moz_da' => max(1, $dr - fake()->numberBetween(2, 9)),
                    'semrush_as' => max(1, $dr - fake()->numberBetween(0, 12)),
                    'spam_score' => $spamScore,
                    'referring_domains' => (int) ($traffic / fake()->numberBetween(18, 45)),
                    'organic_keywords' => (int) ($traffic / fake()->numberBetween(2, 6)),
                    'traffic_by_country' => [
                        $website->country->code => fake()->numberBetween(45, 80),
                        'US' => fake()->numberBetween(5, 25),
                        'GB' => fake()->numberBetween(2, 12),
                    ],
                    'source' => MetricSource::Ahrefs,
                ],
            );
        }
    }
}
