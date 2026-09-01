<x-layouts.marketing
    title="Pricing"
    description="Publinza charges per placement. Prices are set per site, funds stay frozen until the link is verified, and there is no subscription or minimum spend."
    :schema="$schema">

    <x-ui.section
        title="You pay per placement"
        lead="No subscription, no minimum spend, no expiring credits. Each site carries its own price.">

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            @foreach ([
                ['Article placement', $priceBands['article'], 'We publish a new article on the site with your link in the body. This is what most buyers want.'],
                ['Link insertion', $priceBands['insertion'], 'We add your link to an article already published and indexed on the site. Cheaper, and live faster.'],
                ['Homepage placement', $priceBands['homepage'], 'A link from the site\'s homepage, listed separately because it is not editorial. Offered on some sites only.'],
            ] as [$heading, $band, $body])
                <div class="rounded-card border border-subtle bg-card p-6 shadow-card">
                    <h2 class="font-sora text-md font-semibold text-ink-900">{{ $heading }}</h2>
                    <p class="num mt-3 font-sora text-xl font-semibold text-ink-900">{{ $band }}</p>
                    <p class="mt-3 text-base leading-relaxed text-ink-700">{{ $body }}</p>
                </div>
            @endforeach
        </div>

        <p class="mt-6 max-w-3xl text-base text-ink-500">
            Ranges reflect the current catalog. A site's price tracks its traffic and domain rating,
            so the strongest sites sit at the top of each band.
        </p>
    </x-ui.section>

    <x-ui.section tone="canvas" title="What is and is not included">
        <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
            <div>
                <h3 class="font-sora text-md font-semibold text-ink-900">Included in every placement</h3>
                <ul class="mt-3 flex flex-col gap-2 text-base text-ink-700">
                    @foreach ([
                        'Publication inside the window listed on the site',
                        'The live URL, sent to you for verification',
                        'One follow link to your page, unless the site is marked nofollow',
                        'Replacement or refund if the link comes down inside 12 months',
                    ] as $item)
                        <li class="flex gap-2.5">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
                                 aria-hidden="true" class="mt-0.5 shrink-0 text-success">
                                <path d="m20 6-11 11-5-5" />
                            </svg>
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h3 class="font-sora text-md font-semibold text-ink-900">Priced separately</h3>
                <ul class="mt-3 flex flex-col gap-2 text-base text-ink-700">
                    @foreach ([
                        'Writing, if you would rather not supply the article',
                        'Express publication, where a site offers it',
                        'Homepage and banner placements',
                    ] as $item)
                        <li class="flex gap-2.5">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.75" stroke-linecap="round" aria-hidden="true"
                                 class="mt-0.5 shrink-0 text-ink-500">
                                <path d="M12 5v14M5 12h14" />
                            </svg>
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </x-ui.section>

    <x-ui.section title="How the money moves">
        <ol class="grid grid-cols-1 gap-8 md:grid-cols-3">
            @foreach ([
                ['You top up a balance', 'Add funds by card. The balance sits in your account as available money.'],
                ['Paying freezes it', 'Checking out moves the order total from available into frozen. It is committed to that order and cannot be spent twice.'],
                ['Approval releases it', 'Once you verify the live link, the frozen amount is released to us. Cancel or reject, and it returns to available.'],
            ] as $index => [$heading, $body])
                <li>
                    <span aria-hidden="true"
                          class="num flex size-9 items-center justify-center rounded-pill bg-brand font-sora text-base font-semibold text-white">
                        {{ $index + 1 }}
                    </span>
                    <h3 class="mt-4 font-sora text-md font-semibold text-ink-900">{{ $heading }}</h3>
                    <p class="mt-2 text-base leading-relaxed text-ink-700">{{ $body }}</p>
                </li>
            @endforeach
        </ol>

        <div class="mt-8">
            <x-ui.button size="lg" :href="config('publinza.app_url').'/signup'">Create an account</x-ui.button>
        </div>
    </x-ui.section>

</x-layouts.marketing>
