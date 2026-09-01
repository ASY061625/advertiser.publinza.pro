@props(['items'])

{{--
    Native <details>, so the accordion opens with JavaScript switched off and
    the answers are in the HTML for crawlers rather than injected on click.
--}}
<div class="divide-y divide-ink-300 rounded-card border border-subtle bg-card">
    @foreach ($items as $item)
        <details class="group px-5 py-4" @if ($loop->first) open @endif>
            <summary class="flex cursor-pointer items-center justify-between gap-4 font-sora text-md font-medium text-ink-900 marker:content-none [&::-webkit-details-marker]:hidden">
                {{ $item['question'] }}
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.75" stroke-linecap="round" aria-hidden="true"
                     class="shrink-0 text-ink-500 transition-transform duration-fast group-open:rotate-180">
                    <path d="m6 9 6 6 6-6" />
                </svg>
            </summary>
            <div class="mt-3 max-w-2xl text-base leading-relaxed text-ink-700">
                {{ $item['answer'] }}
            </div>
        </details>
    @endforeach
</div>
