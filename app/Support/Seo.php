<?php

declare(strict_types=1);

namespace App\Support;

/**
 * JSON-LD builders for the marketing site.
 *
 * Kept in one place so the Organization block cannot drift between pages —
 * conflicting company details across a site is worse than omitting them.
 */
final class Seo
{
    /**
     * @return array<string, mixed>
     */
    public static function organization(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => 'Publinza',
            'legalName' => 'Publinza Media Ltd',
            'url' => url('/'),
            'logo' => asset('images/og/logo.png'),
            'email' => 'hello@publinza.pro',
            'foundingDate' => '2021',
            'vatID' => 'IE4821903T',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => '12 Hanover Quay',
                'addressLocality' => 'Dublin',
                'postalCode' => 'D02 K5X8',
                'addressCountry' => 'IE',
            ],
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'email' => 'hello@publinza.pro',
                'availableLanguage' => ['en'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'name' => 'Publinza',
            'url' => url('/'),
            'publisher' => ['@id' => url('/').'#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => route('catalog').'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * @param  list<array{question: string, answer: string}>  $items
     * @return array<string, mixed>
     */
    public static function faqPage(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $item): array => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
            ], $items),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function blogPosting(
        string $title,
        string $description,
        string $url,
        string $image,
        string $author,
        string $publishedAt,
    ): array {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $title,
            'description' => $description,
            'url' => $url,
            'image' => $image,
            'datePublished' => $publishedAt,
            'author' => ['@type' => 'Person', 'name' => $author],
            'publisher' => ['@id' => url('/').'#organization'],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        ];
    }

    /**
     * @param  list<array{name: string, url: string}>  $crumbs
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $crumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(static fn (array $crumb, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ], $crumbs, array_keys($crumbs)),
        ];
    }
}
