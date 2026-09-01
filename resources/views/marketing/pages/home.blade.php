<x-layouts.marketing
    title="Guest posts on sites we own"
    description="Publinza sells guest posts and link placements on a network of sites we own and run ourselves. Fixed publication windows, traffic figures refreshed monthly, and a replacement or refund if a link comes down inside 12 months."
    :schema="$schema">

    {{-- 1. Hero -------------------------------------------------------------
         Two columns: the promise and a live search on the left, a real slice of
         the catalog on the right. A visitor sees the actual product, with real
         numbers, before scrolling. --}}
    <section class="border-b border-subtle bg-card">
        <div class="mx-auto grid max-w-content grid-cols-1 gap-12 px-6 py-16 lg:grid-cols-2 lg:gap-16 lg:py-24">
            <div class="flex flex-col justify-center">
                <h1 class="max-w-xl font-sora text-2xl font-semibold leading-tight text-ink-900 lg:text-3xl">
                    Every site in this catalog belongs to us
                </h1>

                <p class="mt-5 max-w-xl text-md leading-relaxed text-ink-700">
                    Publinza is not a broker. We own and run all
                    {{ $networkSize }} sites in the network, so there is no publisher to chase, no
                    reseller taking a cut, and one company accountable when a link needs fixing.
                </p>

                <form action="{{ route('catalog') }}" method="get" class="mt-8 max-w-md" role="search">
                    <label for="hero-search" class="sr-only">Search a domain or niche</label>
                    <div class="relative">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.75" stroke-linecap="round" aria-hidden="true"
                             class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-500">
                            <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
                        </svg>
                        <input id="hero-search"
                               name="q"
                               type="search"
                               data-search-input
                               autocomplete="off"
                               placeholder="Search a domain or niche"
                               class="h-11 w-full rounded-input border border-subtle bg-card pl-10 pr-28 text-base text-ink-900 placeholder:text-ink-500 hover:border-strong">
                        <x-ui.button type="submit" class="absolute right-1.5 top-1/2 -translate-y-1/2">
                            Search
                        </x-ui.button>
                    </div>
                    <p class="mt-2 text-sm text-ink-500">
                        Type to filter the sites shown here, or press Search for the full catalog.
                    </p>
                </form>
            </div>

            <div class="lg:pl-4">
                <x-marketing.catalog-table :rows="$heroRows" :ranges="$ranges" searchable />
                <p class="mt-3 text-sm text-ink-500">
                    Three of {{ $networkSize }} sites. Traffic and DR refreshed
                    {{ $metricsRefreshedAt }}.
                </p>
            </div>
        </div>
    </section>

    {{-- 2. Three-step buying flow ---------------------------------------------
         Numbered markers, because this genuinely is a sequence. --}}
    <x-ui.section tone="canvas" title="Three steps from signing up to a live link">
        <ol class="grid grid-cols-1 gap-8 md:grid-cols-3">
            @foreach ([
                ['Create a project', 'Tell us the page you want linked and the anchor text you want used. One project per campaign, with folders if you run several landing pages.'],
                ['Pick sites from the catalog', 'Filter by traffic, domain rating, category and price. Add what fits to the cart and pay from your balance. Funds stay frozen, not spent.'],
                ['Approve the published link', 'We write and publish inside the window shown on each site. Check the live URL, approve it, and the frozen funds are released.'],
            ] as $index => [$heading, $body])
                <li class="relative">
                    <span aria-hidden="true"
                          class="num flex size-9 items-center justify-center rounded-pill bg-brand font-sora text-base font-semibold text-white">
                        {{ $index + 1 }}
                    </span>
                    <h3 class="mt-4 font-sora text-md font-semibold text-ink-900">{{ $heading }}</h3>
                    <p class="mt-2 text-base leading-relaxed text-ink-700">{{ $body }}</p>
                </li>
            @endforeach
        </ol>
    </x-ui.section>

    {{-- 3. Catalog preview ------------------------------------------------- --}}
    <x-ui.section
        title="Eight sites from the network"
        lead="Live rows from the same database the app reads. Filter by category, then open the full catalog.">

        <div class="mb-5 flex flex-wrap gap-2">
            <a href="{{ route('catalog') }}"
               data-category-chip=""
               data-active="true"
               aria-pressed="true"
               class="rounded-pill border border-subtle px-3.5 py-1.5 text-base transition-colors duration-fast
                      data-[active=true]:border-brand data-[active=true]:bg-brand-subtle data-[active=true]:text-brand
                      data-[active=false]:text-ink-700 hover:bg-sunken">
                All categories
            </a>
            @foreach ($categories as $category)
                <a href="{{ route('catalog', ['category' => $category['slug']]) }}"
                   data-category-chip="{{ $category['slug'] }}"
                   data-active="false"
                   aria-pressed="false"
                   class="rounded-pill border border-subtle px-3.5 py-1.5 text-base transition-colors duration-fast
                          data-[active=true]:border-brand data-[active=true]:bg-brand-subtle data-[active=true]:text-brand
                          data-[active=false]:text-ink-700 hover:bg-sunken">
                    {{ $category['name'] }}
                </a>
            @endforeach
        </div>

        <x-marketing.catalog-table :rows="$previewRows" :ranges="$ranges" filterable />

        <div class="mt-6">
            <x-ui.button size="lg" :href="config('publinza.app_url').'/register'">See all sites</x-ui.button>
        </div>
    </x-ui.section>

    {{-- 4. Why single-owner --------------------------------------------------
         Four claims, each with the plain-language proof rather than an adjective. --}}
    <x-ui.section
        tone="canvas"
        title="What owning the network actually changes"
        lead="Four things a broker cannot promise, because they do not control the sites they sell.">

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            @foreach ([
                ['Fixed publication windows', 'Each site shows a window — 24 hours to 7 days. We control the editorial calendar, so that window is a commitment, not an estimate passed on from a publisher who may not answer.'],
                ['Traffic figures refreshed monthly', 'Every site is re-measured each month and the previous readings stay visible. You can see whether a site is growing or sliding before you buy, instead of a number screenshotted a year ago.'],
                ['No reseller markup', 'You pay us and we publish. There is no publisher invoice behind ours, so the catalog price is the whole price and it does not move because a middleman raised theirs.'],
                ['Replacement or refund for 12 months', 'If a link is removed or the page is taken down within 12 months of publication, we place it again on a site of equal or better metrics, or refund it. We can promise that because we own the page.'],
            ] as [$heading, $body])
                <div class="rounded-card border border-subtle bg-card p-6 shadow-card">
                    <h3 class="font-sora text-md font-semibold text-ink-900">{{ $heading }}</h3>
                    <p class="mt-2 text-base leading-relaxed text-ink-700">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </x-ui.section>

    {{-- 5. Pricing explainer ----------------------------------------------- --}}
    <x-ui.section
        title="You pay per placement"
        lead="No subscription, no minimum spend, no credits that expire.">

        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            @foreach ([
                ['One price per site', 'Each site lists its own price for an article placement or a link insertion. Add a writing fee only if you want us to write it.'],
                ['Funds freeze, they do not vanish', 'Paying moves money from your available balance into a frozen balance held against that order. It is committed, not spent.'],
                ['Released when you approve', 'We release the frozen amount once the link is live and verified. Cancel before publication, or have a placement rejected, and it returns to your balance.'],
            ] as [$heading, $body])
                <div>
                    <h3 class="font-sora text-md font-semibold text-ink-900">{{ $heading }}</h3>
                    <p class="mt-2 text-base leading-relaxed text-ink-700">{{ $body }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-8 flex flex-wrap items-center gap-3">
            <x-ui.button size="lg" :href="route('pricing')">See how pricing works</x-ui.button>
            <x-ui.button size="lg" variant="secondary" :href="route('how-it-works')">Read the process</x-ui.button>
        </div>
    </x-ui.section>

    {{-- 6. FAQ -------------------------------------------------------------- --}}
    <x-ui.section tone="canvas" id="faq" title="Questions we get asked">
        <x-marketing.faq :items="$faq" />
    </x-ui.section>

</x-layouts.marketing>
