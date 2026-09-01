<x-layouts.marketing
    title="About"
    description="Publinza is a small team in Dublin that builds and runs its own network of editorial sites, and sells placements on them directly."
    :schema="$schema">

    <x-ui.section title="We build the sites we sell placements on">
        <div class="max-w-3xl space-y-5 text-md leading-relaxed text-ink-700">
            <p>
                Publinza started in 2021 as a set of niche sites we ran for our own projects. Agencies
                kept asking to buy placements on them, and kept telling us the same thing about the
                alternative: they were paying a broker, the broker was paying a publisher, and when a
                link disappeared nobody would own the problem.
            </p>
            <p>
                So we went the other way. Rather than building a marketplace and inviting publishers in,
                we kept buying and building sites, and sold placements on those alone. Today the network
                is {{ $networkSize }} sites across
                {{ $categoryCount }} categories and {{ $languageCount }} languages. We employ the editors.
                We hold the domains. When something needs fixing, there is nobody for us to escalate to.
            </p>
            <p>
                That has costs, and we would rather be straight about them. Our catalog is smaller than a
                marketplace's, because every site has to be one we were willing to buy and run. We cannot
                place on a specific site you have in mind unless we already own it. And we grow slowly,
                because acquiring a site properly takes months.
            </p>
            <p>
                What you get in return is a single accountable counterparty, prices without a middleman's
                margin, and figures we measured ourselves.
            </p>
        </div>
    </x-ui.section>

    <x-ui.section tone="canvas" title="How we run it">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            @foreach ([
                ['Editorial first', 'Every site has a real editor and a real publishing schedule. Placements go into that schedule; they do not replace it. A site that only publishes paid posts stops being worth buying from.'],
                ['Measured monthly', 'We re-measure traffic, domain rating, domain authority and spam score for the whole network each month, and keep the history. If a site slides, you will see it before you buy.'],
                ['Small on purpose', 'We would rather run 60 sites we know well than list 60,000 we have never seen. It is the only way the 12-month guarantee means anything.'],
            ] as [$heading, $body])
                <div>
                    <h3 class="font-sora text-md font-semibold text-ink-900">{{ $heading }}</h3>
                    <p class="mt-2 text-base leading-relaxed text-ink-700">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </x-ui.section>

    <x-ui.section title="Company details">
        <dl class="grid max-w-2xl grid-cols-1 gap-x-8 gap-y-4 text-base sm:grid-cols-2">
            @foreach ([
                ['Registered name', 'Publinza Media Ltd'],
                ['Company number', '742118 (Ireland)'],
                ['VAT number', 'IE4821903T'],
                ['Registered office', '12 Hanover Quay, Dublin 2, D02 K5X8, Ireland'],
                ['Founded', '2021'],
                ['Contact', 'hello@publinza.pro'],
            ] as [$term, $value])
                <div>
                    <dt class="text-sm text-ink-500">{{ $term }}</dt>
                    <dd class="mt-0.5 text-ink-900">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </x-ui.section>

</x-layouts.marketing>
