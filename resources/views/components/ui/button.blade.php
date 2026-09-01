@props([
    'href' => null,
    'variant' => 'primary',
    'size' => 'md',
    'disabled' => false,
])

@php
    // Mirrors resources/js/shared/ui/Button.tsx. The marketing site is Blade, so
    // the two are twins rather than one component — keep them in step.
    $variants = [
        'primary' => 'bg-brand text-white hover:bg-brand-hover active:bg-brand-pressed',
        'secondary' => 'border border-subtle bg-card text-ink-700 hover:bg-sunken active:bg-ink-300',
        'ghost' => 'text-ink-700 hover:bg-sunken active:bg-ink-300',
        'danger' => 'bg-danger text-white hover:bg-danger-pressed',
    ];

    $sizes = [
        'sm' => 'h-8 gap-1.5 px-3 text-sm',
        'md' => 'h-9 gap-2 px-4 text-base',
        'lg' => 'h-11 gap-2 px-5 text-md',
    ];

    $classes = implode(' ', [
        'inline-flex select-none items-center justify-center whitespace-nowrap rounded-button font-sora font-medium',
        'transition-colors duration-fast ease-standard',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
        $disabled ? 'pointer-events-none opacity-50' : '',
    ]);
@endphp

@if ($href && ! $disabled)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $attributes->get('type', 'button') }}"
            @disabled($disabled)
            {{ $attributes->merge(['class' => $classes])->except('type') }}>{{ $slot }}</button>
@endif
