@props([
    'rows',
    'ranges',
    'searchable' => false,
    'filterable' => false,
])

{{--
    A real slice of the catalog, rendered server-side from the database.

    The quant-bars scale against the catalog-wide min/max passed in `$ranges`,
    not against these few rows — same rule as the app, so a visitor comparing
    the preview with the real catalog after signing up sees consistent shapes.
--}}
<div class="overflow-x-auto rounded-card border border-subtle bg-card shadow-card">
    <table class="w-full border-collapse text-left text-base">
        <caption class="sr-only">Sites in the Publinza network</caption>
        <thead>
            <tr>
                <th scope="col" class="border-b border-subtle bg-sunken px-4 py-3 text-sm font-medium text-ink-500">Site</th>
                <th scope="col" class="border-b border-subtle bg-sunken px-4 py-3 text-sm font-medium text-ink-500">Category</th>
                <th scope="col" class="num border-b border-subtle bg-sunken px-4 py-3 text-right text-sm font-medium text-ink-500">Monthly traffic</th>
                <th scope="col" class="num border-b border-subtle bg-sunken px-4 py-3 text-right text-sm font-medium text-ink-500">DR</th>
                <th scope="col" class="num border-b border-subtle bg-sunken px-4 py-3 text-right text-sm font-medium text-ink-500">Price</th>
                <th scope="col" class="w-[128px] border-b border-subtle bg-sunken px-4 py-3"><span class="sr-only">Action</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr class="h-row-catalog transition-colors duration-fast hover:bg-row-hover"
                    @if ($searchable) data-search-row="{{ $row['domain'].' '.$row['category'] }}" @endif
                    @if ($filterable) data-category="{{ $row['categorySlug'] }}" @endif>
                    <td class="border-b border-subtle px-4">
                        <span class="font-medium text-ink-900">{{ $row['domain'] }}</span>
                        <span class="ml-2 text-sm uppercase text-ink-500">{{ $row['language'] }}</span>
                    </td>
                    <td class="border-b border-subtle px-4 text-ink-700">{{ $row['category'] }}</td>
                    <td class="border-b border-subtle px-4">
                        <x-ui.quant-bar :value="$row['traffic']"
                                        :min="$ranges['traffic'][0]"
                                        :max="$ranges['traffic'][1]"
                                        :format="\App\Support\Format::compact($row['traffic'])" />
                    </td>
                    <td class="border-b border-subtle px-4">
                        <x-ui.quant-bar :value="$row['domainRating']"
                                        :min="$ranges['domainRating'][0]"
                                        :max="$ranges['domainRating'][1]"
                                        :format="(string) $row['domainRating']" />
                    </td>
                    <td class="num border-b border-subtle px-4 text-right font-medium text-ink-900">
                        {{ \App\Support\Format::money($row['priceCents']) }}
                    </td>
                    <td class="border-b border-subtle px-4 text-right">
                        {{-- Disabled on the marketing site: buying happens in the app. --}}
                        <x-ui.button size="sm" variant="secondary" disabled
                                     aria-label="Add {{ $row['domain'] }} to cart — sign in first">
                            Add to cart
                        </x-ui.button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($searchable)
        <p data-search-empty hidden class="px-4 py-10 text-center text-base text-ink-500">
            No sites match that search. Try a broader term, or browse the full catalog.
        </p>
    @endif
    @if ($filterable)
        <p data-category-empty hidden class="px-4 py-10 text-center text-base text-ink-500">
            No sites in that category yet.
        </p>
    @endif
</div>
