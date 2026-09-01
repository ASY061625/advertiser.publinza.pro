<x-layouts.marketing :title="$title" :description="$description" :schema="$schema">

    <article class="mx-auto max-w-content px-6 py-16">
        <h1 class="font-sora text-2xl font-semibold text-ink-900">{{ $title }}</h1>
        <p class="mt-3 text-base text-ink-500">Last updated {{ $updatedAt }}.</p>

        <div class="mt-10 max-w-3xl">
            @foreach ($sections as $section)
                <section class="mb-9">
                    <h2 class="font-sora text-md font-semibold text-ink-900">{{ $section['heading'] }}</h2>
                    @foreach ($section['paragraphs'] as $paragraph)
                        <p class="mt-3 text-base leading-relaxed text-ink-700">{{ $paragraph }}</p>
                    @endforeach

                    @if (! empty($section['list']))
                        <ul class="mt-3 flex list-disc flex-col gap-2 pl-5 text-base leading-relaxed text-ink-700">
                            @foreach ($section['list'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>

        <p class="mt-4 max-w-3xl text-base text-ink-500">
            Questions about this document? Email
            <a href="mailto:legal@publinza.pro" class="text-brand underline">legal@publinza.pro</a>.
        </p>
    </article>

</x-layouts.marketing>
