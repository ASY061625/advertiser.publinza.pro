<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The three legal documents, as structured content rather than markup.
 *
 * Kept in PHP so they are version-controlled and reviewable in a diff. They are
 * a starting point drafted to describe how the product actually behaves; have a
 * solicitor review them before launch.
 *
 * @phpstan-type Section array{heading: string, paragraphs: list<string>, list?: list<string>}
 */
final class LegalDocuments
{
    public const UPDATED_AT = '1 September 2026';

    private const COMPANY = 'Publinza Media Ltd, a company registered in Ireland under number 742118, '
        .'with its registered office at 12 Hanover Quay, Dublin 2, D02 K5X8, Ireland ("Publinza", "we", "us")';

    /**
     * @return list<array{heading: string, paragraphs: list<string>, list?: list<string>}>
     */
    public static function terms(): array
    {
        return [
            [
                'heading' => 'Who these terms are with',
                'paragraphs' => [
                    'These terms are between you and '.self::COMPANY.'. They apply when you create an account, browse the catalog, or buy a placement.',
                    'By creating an account you confirm you are acting for a business and have authority to enter these terms on its behalf.',
                ],
            ],
            [
                'heading' => 'What we sell',
                'paragraphs' => [
                    'We sell placements on websites we own and operate. A placement is either a new article published on one of our sites containing your link, or a link inserted into an article already published there.',
                    'We do not sell placements on websites belonging to third parties, and we do not act as an agent or broker for any publisher.',
                ],
            ],
            [
                'heading' => 'Orders and payment',
                'paragraphs' => [
                    'Prices are shown per site in the catalog and are exclusive of VAT, which is added where applicable.',
                    'You fund an account balance in advance. Placing an order moves the order total from your available balance to a frozen balance held against that order. Frozen funds remain yours until the placement is published and verified.',
                    'We release frozen funds to ourselves when you approve the published link, or automatically three days after publication if you have not raised an issue in that time.',
                ],
            ],
            [
                'heading' => 'Editorial control',
                'paragraphs' => [
                    'Our editors decide what appears on our sites. We may decline a brief, ask for changes to anchor text or claims, or refuse a subject a particular site does not accept.',
                    'Where we decline before publication, the placement is cancelled and the frozen funds return to your available balance in full.',
                ],
            ],
            [
                'heading' => 'Publication windows',
                'paragraphs' => [
                    'Each site states a publication window. We aim to publish inside it and will tell you promptly if we cannot.',
                    'If we miss the stated window by more than five working days and you have not caused the delay, you may cancel the placement for a full refund.',
                ],
            ],
            [
                'heading' => 'Link duration',
                'paragraphs' => [
                    'We keep published placements live for at least 12 months from publication. If a link is removed, altered so it no longer points to your page, or its page is taken down within that period, our refund policy sets out what we do.',
                ],
            ],
            [
                'heading' => 'What you are responsible for',
                'paragraphs' => [
                    'You confirm that any content you supply is yours to publish, is accurate, and does not infringe anyone else\'s rights.',
                ],
                'list' => [
                    'Content you supply must not be defamatory, misleading, or unlawful',
                    'You must hold the rights to any images you provide',
                    'Target pages must not host malware or deceptive content',
                    'You must not use placements to promote a subject a site has declined',
                ],
            ],
            [
                'heading' => 'What we do not promise',
                'paragraphs' => [
                    'We do not promise any particular search ranking, traffic, or commercial outcome from a placement. Nobody honestly can. We promise publication, the metrics we state, and the link duration set out above.',
                ],
            ],
            [
                'heading' => 'Liability',
                'paragraphs' => [
                    'Our total liability arising from any placement is limited to the amount you paid for that placement.',
                    'Nothing in these terms limits liability for death or personal injury caused by negligence, for fraud, or for anything else that cannot be limited by law.',
                ],
            ],
            [
                'heading' => 'Ending the agreement',
                'paragraphs' => [
                    'You may close your account at any time. Any unspent available balance is returned to its original payment method. Frozen funds are settled once the placements they are held against complete or are cancelled.',
                    'We may suspend an account that breaches these terms, and will explain why.',
                ],
            ],
            [
                'heading' => 'Governing law',
                'paragraphs' => [
                    'These terms are governed by the laws of Ireland, and the courts of Ireland have exclusive jurisdiction.',
                ],
            ],
        ];
    }

    /**
     * @return list<array{heading: string, paragraphs: list<string>, list?: list<string>}>
     */
    public static function privacy(): array
    {
        return [
            [
                'heading' => 'Who controls your data',
                'paragraphs' => [
                    self::COMPANY.' is the data controller for personal data processed through this site and the Publinza application. Contact us at privacy@publinza.pro.',
                ],
            ],
            [
                'heading' => 'What we collect',
                'paragraphs' => ['We collect only what we need to run an account and deliver placements.'],
                'list' => [
                    'Account details: name, email address, company, country, VAT number and phone number',
                    'Billing data: invoices, wallet transactions, and a payment provider token — we never see or store your full card number',
                    'Content you submit: projects, briefs, articles and messages',
                    'Technical data: IP address, browser and pages viewed, collected only if you accept analytics cookies',
                ],
            ],
            [
                'heading' => 'Why we process it',
                'paragraphs' => [
                    'To perform our contract with you: creating your account, taking payment, publishing placements and supporting you afterwards.',
                    'To meet legal obligations: keeping accounting and tax records.',
                    'For our legitimate interests: preventing fraud and abuse, and keeping the service secure.',
                    'With your consent: analytics cookies, and marketing email if you opt in. You can withdraw consent at any time.',
                ],
            ],
            [
                'heading' => 'Cookies',
                'paragraphs' => [
                    'We set one essential cookie to keep you signed in and protect forms against cross-site request forgery. It cannot be switched off without breaking the service.',
                    'Analytics cookies are off until you accept them. The analytics script is not loaded at all until you do, so declining means nothing is fetched and nothing is set. You can change your answer by clearing this site\'s data in your browser.',
                ],
            ],
            [
                'heading' => 'Who we share it with',
                'paragraphs' => ['We do not sell personal data. We share it only with processors who help us run the service.'],
                'list' => [
                    'Our payment provider, to take and refund payments',
                    'Our hosting and email providers, to run the service and send transactional email',
                    'Our analytics provider, only where you have accepted analytics cookies',
                    'Tax and legal advisers, and authorities where we are required by law',
                ],
            ],
            [
                'heading' => 'How long we keep it',
                'paragraphs' => [
                    'Account and placement records are kept for as long as your account is open, and for seven years afterwards where they form part of our accounting records.',
                    'Messages and briefs are deleted 24 months after an account closes. Analytics data is retained for 14 months.',
                ],
            ],
            [
                'heading' => 'Your rights',
                'paragraphs' => ['Under the GDPR you may ask us to do any of the following, and we will respond within one month.'],
                'list' => [
                    'Give you a copy of the personal data we hold about you',
                    'Correct anything inaccurate',
                    'Delete data we no longer have a lawful reason to keep',
                    'Restrict or object to a particular use',
                    'Export your data in a portable format',
                    'Complain to the Irish Data Protection Commission if you are not satisfied',
                ],
            ],
            [
                'heading' => 'Transfers outside the EEA',
                'paragraphs' => [
                    'Some of our processors are based outside the European Economic Area. Where that is the case we rely on the European Commission\'s standard contractual clauses.',
                ],
            ],
        ];
    }

    /**
     * @return list<array{heading: string, paragraphs: list<string>, list?: list<string>}>
     */
    public static function refunds(): array
    {
        return [
            [
                'heading' => 'Before publication',
                'paragraphs' => [
                    'You can cancel any placement that has not yet been published, for any reason, and the full amount returns to your available balance immediately. Nothing has been spent at that point — your funds were frozen, not taken.',
                ],
            ],
            [
                'heading' => 'If we decline your brief',
                'paragraphs' => [
                    'If our editors decline a brief and you do not want to revise it, the placement is cancelled and refunded in full.',
                ],
            ],
            [
                'heading' => 'If we miss the publication window',
                'paragraphs' => [
                    'If we publish more than five working days after the window stated on the site, and the delay is not caused by waiting on you, you may cancel for a full refund.',
                ],
            ],
            [
                'heading' => 'After publication',
                'paragraphs' => [
                    'You have three days from the moment we send you the live URL to check the placement. If the article is on the wrong page, uses the wrong anchor text, or the link is the wrong type, tell us within that window and we will correct it or refund the placement.',
                    'After you approve the link, or after the three days pass without an issue raised, the placement is complete and the funds are released to us.',
                ],
            ],
            [
                'heading' => 'The 12-month link guarantee',
                'paragraphs' => [
                    'We keep placements live for at least 12 months. If within that period a link is removed, altered so it no longer points to your page, or its page is taken down, tell us and we will do one of the following, at your choice.',
                ],
                'list' => [
                    'Restore the original link on the original page',
                    'Place it again on another site in the network with equal or better traffic and domain rating, at no cost',
                    'Refund the placement in full to your available balance',
                ],
            ],
            [
                'heading' => 'What is not refundable',
                'paragraphs' => [
                    'We do not refund a placement because it did not produce a ranking or traffic change. We do not promise those outcomes and cannot refund against them.',
                    'We do not refund a placement removed because the content you supplied turned out to be unlawful, infringing or misleading.',
                    'Writing fees for an article we have already produced and delivered for review are not refundable if you then cancel for a reason unrelated to its quality.',
                ],
            ],
            [
                'heading' => 'Withdrawing your balance',
                'paragraphs' => [
                    'Unspent available balance can be withdrawn at any time to the original payment method. Withdrawals are processed within five working days. Frozen funds cannot be withdrawn until the placements they are held against complete or are cancelled.',
                ],
            ],
            [
                'heading' => 'How to ask',
                'paragraphs' => [
                    'Raise it from the order in the app, or email billing@publinza.pro with the order number. We answer within one working day and settle agreed refunds within five.',
                ],
            ],
        ];
    }
}
