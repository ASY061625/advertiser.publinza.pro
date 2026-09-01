{{--
    Consent gate.

    The analytics script is not in this page. Its URL sits in a data attribute
    and is only turned into a <script> tag after someone accepts, so a visitor
    who declines — or who never answers — never fetches it and it can set
    nothing. `hidden` by default: the island unhides the banner only when there
    is no stored decision, so returning visitors are not asked twice.
--}}
<div data-consent-banner
     hidden
     role="dialog"
     aria-label="Cookies"
     aria-live="polite"
     class="fixed inset-x-0 bottom-0 z-50 border-t border-subtle bg-card shadow-card">
    <div class="mx-auto flex max-w-content flex-col gap-4 px-6 py-4 md:flex-row md:items-center md:justify-between">
        <p class="max-w-2xl text-base text-ink-700">
            We use analytics cookies to see which pages are useful. They stay off until you accept.
            Read the <a href="{{ route('privacy') }}" class="text-brand underline">privacy policy</a>.
        </p>

        <div class="flex shrink-0 items-center gap-2">
            <x-ui.button variant="secondary" data-consent-decline>Decline</x-ui.button>
            <x-ui.button data-consent-accept>Accept analytics</x-ui.button>
        </div>
    </div>
</div>

@if (config('services.analytics.script'))
    <span data-analytics="{{ config('services.analytics.script') }}" hidden></span>
@endif
