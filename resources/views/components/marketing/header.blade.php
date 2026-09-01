@php
    $nav = [
        ['label' => 'Catalog', 'route' => 'catalog'],
        ['label' => 'How it works', 'route' => 'how-it-works'],
        ['label' => 'Pricing', 'route' => 'pricing'],
        ['label' => 'Blog', 'route' => 'blog.index'],
    ];
@endphp

<header class="sticky top-0 z-40 border-b border-subtle bg-card">
    <div class="mx-auto flex h-header max-w-content items-center justify-between px-6">
        <a href="{{ route('home') }}" class="font-sora text-md font-semibold text-ink-900">
            Publinza
        </a>

        <nav aria-label="Main" class="hidden items-center gap-7 md:flex">
            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}"
                   @if (request()->routeIs($item['route'])) aria-current="page" @endif
                   class="text-base transition-colors duration-fast hover:text-brand
                          {{ request()->routeIs($item['route']) ? 'text-brand' : 'text-ink-700' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="hidden items-center gap-4 md:flex">
            {{-- Both point at the advertiser app on its own subdomain. --}}
            <a href="{{ config('publinza.app_url') }}/login"
               class="text-base text-ink-700 transition-colors duration-fast hover:text-brand">
                Log in
            </a>
            <x-ui.button :href="config('publinza.app_url').'/register'">Create account</x-ui.button>
        </div>

        <button type="button"
                data-nav-toggle
                aria-expanded="false"
                aria-controls="mobile-nav"
                aria-label="Open menu"
                class="flex size-9 items-center justify-center rounded-button text-ink-700 hover:bg-sunken md:hidden">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.75" stroke-linecap="round" aria-hidden="true">
                <path d="M4 7h16M4 12h16M4 17h16" />
            </svg>
        </button>
    </div>

    <div id="mobile-nav" data-nav-menu hidden class="border-t border-subtle bg-card md:hidden">
        <nav aria-label="Main" class="mx-auto flex max-w-content flex-col gap-1 px-6 py-4">
            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}"
                   class="rounded-button px-3 py-2 text-base text-ink-700 hover:bg-sunken">{{ $item['label'] }}</a>
            @endforeach
            <a href="{{ config('publinza.app_url') }}/login"
               class="rounded-button px-3 py-2 text-base text-ink-700 hover:bg-sunken">Log in</a>
            <x-ui.button :href="config('publinza.app_url').'/register'" class="mt-2">Create account</x-ui.button>
        </nav>
    </div>
</header>
