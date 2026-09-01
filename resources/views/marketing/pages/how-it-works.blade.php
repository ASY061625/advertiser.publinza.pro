<x-layouts.marketing
    title="How it works"
    description="From creating a project to approving a live link: how buying a placement on Publinza works, what we do at each step, and what happens if something goes wrong."
    :schema="$schema">

    <x-ui.section
        title="How buying a placement works"
        lead="The whole process, including the parts that are usually left vague.">

        <ol class="flex flex-col gap-10">
            @foreach ([
                ['Create a project', 'A project holds the page you want linked, the anchor text you want used, and any instructions for the writer — tone, products to mention, things to avoid. If you run several landing pages, put them in folders so each set of placements carries the right brief.'],
                ['Choose sites', 'Filter the catalog by traffic, domain rating, domain authority, spam score, category, language and price. Every metric is measured by us and refreshed monthly, and the previous readings stay on record so you can see the direction of travel.'],
                ['Pay from your balance', 'Adding to the cart and paying moves money from your available balance into a frozen balance held against the order. Nothing is paid out yet. If you cancel before we publish, it comes straight back.'],
                ['We write and publish', 'If you asked us to write it, a writer produces a draft against your brief and sends it for review. You approve it or send it back with notes. Once approved, we publish inside the window shown on that site.'],
                ['You verify the live link', 'We post the live URL. You have three days to check it — right page, right anchor, right link type. Approve it and the frozen funds are released to us. Reject it and they return to your balance.'],
                ['We keep it live for 12 months', 'If the link is removed or the page is taken down within 12 months, tell us and we replace it on a site with equal or better metrics, or refund it.'],
            ] as $index => [$heading, $body])
                <li class="flex gap-5">
                    <span aria-hidden="true"
                          class="num flex size-9 shrink-0 items-center justify-center rounded-pill bg-brand font-sora text-base font-semibold text-white">
                        {{ $index + 1 }}
                    </span>
                    <div>
                        <h2 class="font-sora text-md font-semibold text-ink-900">{{ $heading }}</h2>
                        <p class="mt-2 max-w-3xl text-base leading-relaxed text-ink-700">{{ $body }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </x-ui.section>

    <x-ui.section tone="canvas" title="What we do not do">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            @foreach ([
                ['We do not resell other people\'s sites', 'Every domain in the catalog is ours. If a site is not in the catalog, we cannot place on it, and we will say so rather than sourcing it from a broker.'],
                ['We do not sell homepage links as editorial', 'Homepage and banner placements are listed separately and priced separately. An article placement is an article.'],
                ['We do not guarantee rankings', 'Nobody can. We can tell you the traffic, the metrics and the window, and keep the link live. What it does for your rankings depends on your site.'],
            ] as [$heading, $body])
                <div>
                    <h3 class="font-sora text-md font-semibold text-ink-900">{{ $heading }}</h3>
                    <p class="mt-2 text-base leading-relaxed text-ink-700">{{ $body }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-8">
            <x-ui.button size="lg" :href="config('publinza.app_url').'/signup'">Create an account</x-ui.button>
        </div>
    </x-ui.section>

</x-layouts.marketing>
