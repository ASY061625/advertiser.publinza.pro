<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">

        {{-- Advertiser bundle only. The admin entry is never referenced here. --}}
        @routes
        @viteReactRefresh
        @vite(['resources/js/advertiser/main.tsx'])
        @inertiaHead
    </head>
    <body class="min-h-screen bg-surface-canvas font-sans text-base text-ink-700">
        @inertia
    </body>
</html>
