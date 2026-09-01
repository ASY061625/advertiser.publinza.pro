import '../../css/globals.css';

/**
 * The marketing site is server-rendered Blade. This file is the only script it
 * ships: four small progressive enhancements, no framework.
 *
 * Everything here degrades. The FAQ is <details>, the filter chips are links,
 * and the search field is a real <form> that submits to the catalog page — all
 * of it works with JavaScript switched off.
 */

/* ------------------------------------------------------------------ search */

/** Filters the hero's catalog preview as you type, without a round trip. */
function initInlineSearch(): void {
    const input = document.querySelector<HTMLInputElement>('[data-search-input]');
    const rows = document.querySelectorAll<HTMLElement>('[data-search-row]');
    const empty = document.querySelector<HTMLElement>('[data-search-empty]');
    if (!input || rows.length === 0) return;

    input.addEventListener('input', () => {
        const needle = input.value.trim().toLowerCase();
        let visible = 0;

        rows.forEach((row) => {
            const haystack = (row.dataset.searchRow ?? '').toLowerCase();
            const match = needle === '' || haystack.includes(needle);
            row.hidden = !match;
            if (match) visible++;
        });

        if (empty) empty.hidden = visible !== 0;
    });
}

/* ------------------------------------------------------------- filter chips */

/** Category chips on the catalog preview. Each is a link; this just avoids the reload. */
function initCategoryChips(): void {
    const chips = document.querySelectorAll<HTMLAnchorElement>('[data-category-chip]');
    const rows = document.querySelectorAll<HTMLElement>('[data-category]');
    const empty = document.querySelector<HTMLElement>('[data-category-empty]');
    if (chips.length === 0 || rows.length === 0) return;

    chips.forEach((chip) => {
        chip.addEventListener('click', (event) => {
            event.preventDefault();
            const wanted = chip.dataset.categoryChip ?? '';

            chips.forEach((other) => {
                const active = other === chip;
                other.dataset.active = active ? 'true' : 'false';
                other.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            let visible = 0;

            rows.forEach((row) => {
                const match = wanted === '' || row.dataset.category === wanted;
                row.hidden = !match;
                if (match) visible++;
            });

            if (empty) empty.hidden = visible !== 0;
        });
    });
}

/* ---------------------------------------------------------- cookie consent */

const CONSENT_KEY = 'publinza.consent';

type Consent = 'granted' | 'denied';

function readConsent(): Consent | null {
    try {
        const stored = localStorage.getItem(CONSENT_KEY);

        return stored === 'granted' || stored === 'denied' ? stored : null;
    } catch {
        // Private mode, or site data blocked. Treat as undecided and ask again.
        return null;
    }
}

function writeConsent(value: Consent): void {
    try {
        localStorage.setItem(CONSENT_KEY, value);
    } catch {
        // Nothing to do: the banner will simply ask again next visit.
    }
}

/**
 * Loads analytics, and only ever after consent.
 *
 * The measurement script is not in the HTML at all — it is injected here — so
 * an un-consented visitor never fetches it and it can set nothing.
 */
function loadAnalytics(): void {
    const config = document.querySelector<HTMLElement>('[data-analytics]');
    const src = config?.dataset.analytics;
    if (!src || document.querySelector(`script[src="${src}"]`)) return;

    const script = document.createElement('script');
    script.src = src;
    script.defer = true;
    document.head.appendChild(script);
}

function initConsent(): void {
    const banner = document.querySelector<HTMLElement>('[data-consent-banner]');
    if (!banner) return;

    const decided = readConsent();

    if (decided === 'granted') {
        loadAnalytics();

        return;
    }

    if (decided === 'denied') return;

    banner.hidden = false;

    banner.querySelector('[data-consent-accept]')?.addEventListener('click', () => {
        writeConsent('granted');
        banner.hidden = true;
        loadAnalytics();
    });

    banner.querySelector('[data-consent-decline]')?.addEventListener('click', () => {
        writeConsent('denied');
        banner.hidden = true;
    });
}

/* ------------------------------------------------------------------- mobile */

function initMobileNav(): void {
    const toggle = document.querySelector<HTMLButtonElement>('[data-nav-toggle]');
    const menu = document.querySelector<HTMLElement>('[data-nav-menu]');
    if (!toggle || !menu) return;

    toggle.addEventListener('click', () => {
        const open = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
        menu.hidden = open;
    });
}

initInlineSearch();
initCategoryChips();
initConsent();
initMobileNav();
