@props([
    'title' => null,
    'lead' => null,
    'tone' => 'card',
    'id' => null,
])

@php
    $tones = ['card' => 'bg-card', 'canvas' => 'bg-canvas', 'sunken' => 'bg-sunken'];
@endphp

<section @if ($id) id="{{ $id }}" @endif
         {{ $attributes->merge(['class' => 'scroll-mt-20 '.($tones[$tone] ?? $tones['card'])]) }}>
    <div class="mx-auto max-w-content px-6 py-16 lg:py-20">
        @if ($title)
            <h2 class="max-w-3xl font-sora text-xl font-semibold text-ink-900 lg:text-2xl">{{ $title }}</h2>
        @endif
        @if ($lead)
            <p class="mt-3 max-w-2xl text-md text-ink-700">{{ $lead }}</p>
        @endif

        <div class="{{ $title || $lead ? 'mt-10' : '' }}">{{ $slot }}</div>
    </div>
</section>
