<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="Publinza is a guest-post marketplace: buy placements on vetted sites and track every post to publication.">
        <link rel="canonical" href="{{ url()->current() }}">

        {{-- Marketing bundle only. --}}
        @routes
        @viteReactRefresh
        @vite(['resources/js/marketing/main.tsx'])
        @inertiaHead
    </head>
    <body class="min-h-screen bg-surface-card font-sans text-md text-ink-700">
        @inertia
    </body>
</html>
