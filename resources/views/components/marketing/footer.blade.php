@php
    $columns = [
        'Product' => [
            ['Catalog', route('catalog')],
            ['How it works', route('how-it-works')],
            ['Pricing', route('pricing')],
            ['Blog', route('blog.index')],
        ],
        'Company' => [
            ['About', route('about')],
            ['Contact', route('contact')],
        ],
        'Legal' => [
            ['Terms of service', route('terms')],
            ['Privacy policy', route('privacy')],
            ['Refund policy', route('refunds')],
        ],
    ];
@endphp

<footer class="border-t border-subtle bg-sunken">
    <div class="mx-auto max-w-content px-6 py-14">
        <div class="grid grid-cols-2 gap-10 md:grid-cols-5">
            <div class="col-span-2">
                <p class="font-sora text-md font-semibold text-ink-900">Publinza</p>
                <p class="mt-3 max-w-xs text-base text-ink-500">
                    Guest posts and link placements on a network of sites we own and run ourselves.
                </p>
            </div>

            @foreach ($columns as $heading => $links)
                <nav aria-label="{{ $heading }}">
                    <p class="font-sora text-base font-medium text-ink-900">{{ $heading }}</p>
                    <ul class="mt-3 flex flex-col gap-2">
                        @foreach ($links as [$label, $url])
                            <li>
                                <a href="{{ $url }}"
                                   class="text-base text-ink-500 transition-colors duration-fast hover:text-brand">{{ $label }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endforeach
        </div>

        <div class="mt-12 flex flex-col gap-3 border-t border-subtle pt-6 text-sm text-ink-500 md:flex-row md:items-center md:justify-between">
            <p>© {{ now()->year }} Publinza Media Ltd. Registered in Ireland, company number 742118.</p>
            <p>
                <a href="mailto:hello@publinza.pro" class="transition-colors duration-fast hover:text-brand">hello@publinza.pro</a>
                <span class="px-2 text-ink-300" aria-hidden="true">·</span>
                12 Hanover Quay, Dublin 2, D02 K5X8, Ireland
            </p>
        </div>
    </div>
</footer>
