@props([
    'value',
    'min',
    'max',
    'inverted' => false,
    'format' => null,
])

@php
    /**
     * The catalog's signature treatment, ported to Blade for the marketing site.
     *
     * Same rules as resources/js/shared/ui/QuantBar.tsx: tabular digits over a
     * 3px bar scaled against the catalog-wide range, brand blue turning teal in
     * the top quartile. Spam score passes `inverted` because low is good.
     */
    $span = max(0, $max - $min);
    $ratio = $span === 0 ? 0 : ($value - $min) / $span;
    $ratio = min(1, max(0, $ratio));
    $fill = $inverted ? 1 - $ratio : $ratio;
    $topQuartile = $fill >= 0.75;
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col items-end gap-1.5']) }}>
    <span class="num text-base text-ink-900">{{ $format ?? number_format($value) }}</span>
    <span class="block h-[3px] w-full max-w-[92px] overflow-hidden rounded-pill bg-sunken">
        <span class="block h-full rounded-pill {{ $topQuartile ? 'bg-teal' : 'bg-brand' }}"
              style="width: {{ number_format($fill * 100, 1) }}%"></span>
    </span>
</div>
