<x-layouts.marketing
    title="Catalog preview"
    description="A preview of the Publinza network: domains, categories, monthly traffic, domain rating and price. Sign in to filter the full catalog and buy placements."
    :schema="$schema">

    <x-ui.section
        title="The network"
        lead="{{ $networkSize }} sites, all owned and run by us. This preview shows {{ count($rows) }} of them with live figures; the full catalog and its filters are in the app.">

        <form action="{{ route('catalog') }}" method="get" class="mb-6 max-w-md" role="search">
            <label for="catalog-search" class="sr-only">Search a domain or niche</label>
            <div class="relative">
                <input id="catalog-search"
                       name="q"
                       type="search"
                       value="{{ $query }}"
                       data-search-input
                       autocomplete="off"
                       placeholder="Search a domain or niche"
                       class="h-10 w-full rounded-input border border-subtle bg-card px-3 pr-24 text-base text-ink-900 placeholder:text-ink-500 hover:border-strong">
                <x-ui.button type="submit" size="sm" class="absolute right-1.5 top-1/2 -translate-y-1/2">Search</x-ui.button>
            </div>
        </form>

        <div class="mb-5 flex flex-wrap gap-2">
            <a href="{{ route('catalog') }}"
               data-category-chip=""
               data-active="{{ $activeCategory === null ? 'true' : 'false' }}"
               aria-pressed="{{ $activeCategory === null ? 'true' : 'false' }}"
               class="rounded-pill border border-subtle px-3.5 py-1.5 text-base transition-colors duration-fast
                      data-[active=true]:border-brand data-[active=true]:bg-brand-subtle data-[active=true]:text-brand
                      data-[active=false]:text-ink-700 hover:bg-sunken">
                All categories
            </a>
            @foreach ($categories as $category)
                <a href="{{ route('catalog', ['category' => $category['slug']]) }}"
                   data-category-chip="{{ $category['slug'] }}"
                   data-active="{{ $activeCategory === $category['slug'] ? 'true' : 'false' }}"
                   aria-pressed="{{ $activeCategory === $category['slug'] ? 'true' : 'false' }}"
                   class="rounded-pill border border-subtle px-3.5 py-1.5 text-base transition-colors duration-fast
                          data-[active=true]:border-brand data-[active=true]:bg-brand-subtle data-[active=true]:text-brand
                          data-[active=false]:text-ink-700 hover:bg-sunken">
                    {{ $category['name'] }}
                </a>
            @endforeach
        </div>

        @if ($rows === [])
            <div class="flex flex-col items-center gap-4 rounded-card bg-sunken px-6 py-14 text-center">
                <p class="max-w-sm text-md text-ink-700">
                    Nothing matches that search yet. Clear it to see the whole preview.
                </p>
                <x-ui.button variant="secondary" :href="route('catalog')">Clear search</x-ui.button>
            </div>
        @else
            <x-marketing.catalog-table :rows="$rows" :ranges="$ranges" searchable filterable />
        @endif

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <x-ui.button size="lg" :href="config('publinza.app_url').'/signup'">See all sites</x-ui.button>
            <p class="text-base text-ink-500">Free to browse. You only pay when you place.</p>
        </div>
    </x-ui.section>

</x-layouts.marketing>
