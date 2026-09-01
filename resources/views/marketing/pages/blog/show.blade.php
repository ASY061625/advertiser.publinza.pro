<x-layouts.marketing
    :title="$post->title"
    :description="$post->excerpt"
    og-type="article"
    :og-image="asset('images/blog/'.$post->slug.'.png')"
    :schema="$schema">

    <article class="mx-auto max-w-content px-6 py-16">
        <nav aria-label="Breadcrumb" class="mb-6">
            <ol class="flex items-center gap-1.5 text-base text-ink-500">
                <li><a href="{{ route('blog.index') }}" class="hover:text-brand">Blog</a></li>
                <li aria-hidden="true" class="text-ink-300">/</li>
                <li aria-current="page" class="text-ink-700">{{ $post->title }}</li>
            </ol>
        </nav>

        <header class="max-w-3xl">
            <h1 class="font-sora text-2xl font-semibold leading-tight text-ink-900">{{ $post->title }}</h1>
            <p class="mt-4 text-sm text-ink-500">
                <time datetime="{{ $post->published_at->toDateString() }}">
                    {{ $post->published_at->format('j F Y') }}
                </time>
                <span class="px-1.5 text-ink-300" aria-hidden="true">·</span>
                {{ $post->reading_minutes }} min read
                <span class="px-1.5 text-ink-300" aria-hidden="true">·</span>
                {{ $post->author }}
            </p>
        </header>

        <picture>
            <source srcset="{{ asset('images/blog/'.$post->slug.'.webp') }}" type="image/webp">
            <img src="{{ asset('images/blog/'.$post->slug.'.png') }}"
                 alt=""
                 width="1200"
                 height="630"
                 {{-- The cover is the LCP element on this page, so it is eager
                      and high priority rather than lazy. --}}
                 fetchpriority="high"
                 decoding="async"
                 class="mt-8 aspect-[40/21] w-full rounded-card bg-sunken object-cover">
        </picture>

        {{-- Body is authored as trusted Markdown-derived HTML by our own editors,
             stored in blog_posts.body_html. --}}
        <div class="prose-publinza mt-10 max-w-3xl">
            {!! $post->body_html !!}
        </div>

        <footer class="mt-12 max-w-3xl border-t border-subtle pt-8">
            <h2 class="font-sora text-md font-semibold text-ink-900">Buying placements on our network</h2>
            <p class="mt-2 text-base leading-relaxed text-ink-700">
                Every site we write about here is one we own. Browse the catalog to see the current
                figures for each of them.
            </p>
            <div class="mt-5 flex flex-wrap gap-3">
                <x-ui.button :href="route('catalog')">See the catalog</x-ui.button>
                <x-ui.button variant="secondary" :href="route('how-it-works')">How it works</x-ui.button>
            </div>
        </footer>
    </article>

</x-layouts.marketing>
