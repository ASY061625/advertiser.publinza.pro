@props([
    'title',
    'description',
    'canonical' => null,
    'ogImage' => null,
    'ogType' => 'website',
    'schema' => [],
    'noindex' => false,
])

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title }} · Publinza</title>
        <meta name="description" content="{{ $description }}">
        <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
        @if ($noindex)
            <meta name="robots" content="noindex, nofollow">
        @endif

        {{-- Fonts are the one third-party request. Preconnect so the LCP text
             does not wait on a cold DNS lookup and TLS handshake. --}}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

        <meta property="og:type" content="{{ $ogType }}">
        <meta property="og:site_name" content="Publinza">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
        <meta property="og:image" content="{{ $ogImage ?? asset('images/og/default.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta name="twitter:card" content="summary_large_image">

        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

        {{-- One stylesheet, and a module script that defers by default, so
             nothing here blocks the first paint. --}}
        @vite(['resources/js/marketing/main.ts'])

        @foreach ($schema as $block)
            <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endforeach
    </head>

    <body class="flex min-h-screen flex-col bg-card font-sans text-md text-ink-700 antialiased">
        <a href="#main"
           class="sr-only-focusable absolute left-4 top-4 z-50 rounded-button bg-brand px-4 py-2 font-sora text-base font-medium text-white">
            Skip to content
        </a>

        <x-marketing.header />

        <main id="main" class="flex-1">
            {{ $slot }}
        </main>

        <x-marketing.footer />
        <x-marketing.cookie-banner />
    </body>
</html>
