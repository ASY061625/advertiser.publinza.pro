<x-layouts.marketing
    title="Blog"
    description="Notes on link building, how we measure the sites in our network, and what we have learned running editorial sites."
    :schema="$schema">

    <x-ui.section title="Blog" lead="What we have learned running the network, and how we measure it.">
        @if ($posts->isEmpty())
            <div class="flex flex-col items-center gap-4 rounded-card bg-sunken px-6 py-14 text-center">
                <p class="max-w-sm text-md text-ink-700">Nothing published yet. Check back shortly.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <article class="flex flex-col overflow-hidden rounded-card border border-subtle bg-card shadow-card">
                        <a href="{{ route('blog.show', $post->slug) }}" class="block">
                            {{-- Explicit width and height so the card never shifts
                                 while the image loads. --}}
                            <picture>
                                <source srcset="{{ asset('images/blog/'.$post->slug.'.webp') }}" type="image/webp">
                                <img src="{{ asset('images/blog/'.$post->slug.'.png') }}"
                                     alt=""
                                     width="800"
                                     height="420"
                                     loading="lazy"
                                     decoding="async"
                                     class="aspect-[40/21] w-full bg-sunken object-cover">
                            </picture>
                        </a>

                        <div class="flex flex-1 flex-col p-5">
                            <p class="text-sm text-ink-500">
                                <time datetime="{{ $post->published_at->toDateString() }}">
                                    {{ $post->published_at->format('j M Y') }}
                                </time>
                                <span class="px-1.5 text-ink-300" aria-hidden="true">·</span>
                                {{ $post->reading_minutes }} min read
                            </p>

                            <h2 class="mt-2 font-sora text-md font-semibold text-ink-900">
                                <a href="{{ route('blog.show', $post->slug) }}" class="hover:text-brand">{{ $post->title }}</a>
                            </h2>

                            <p class="mt-2 flex-1 text-base leading-relaxed text-ink-700">{{ $post->excerpt }}</p>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-10">{{ $posts->links() }}</div>
        @endif
    </x-ui.section>

</x-layouts.marketing>
