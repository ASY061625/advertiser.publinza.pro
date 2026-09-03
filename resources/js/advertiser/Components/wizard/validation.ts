import type { LandingPageRow, WizardState } from '@shared/types/wizard';

/**
 * The part of the form the landing-page rules actually read.
 *
 * Narrower than WizardState on purpose: the folder editor has landing pages and
 * a promoted URL but none of the wizard's other fields, and both screens have to
 * validate a landing page the same way or one of them is wrong.
 */
export interface LandingPageContext {
    website_url: string;
    landing_pages: LandingPageRow[];
}

export const MAX_TASK_CHARS = 3000;

/**
 * Suffixes under which people register. An approximation of the Public Suffix
 * List, kept in step with App\Support\PublicSuffix — the client decides what to
 * show inline, the server decides what is allowed.
 */
const MULTI_LABEL = new Set([
    'co.uk',
    'org.uk',
    'me.uk',
    'ltd.uk',
    'plc.uk',
    'net.uk',
    'sch.uk',
    'ac.uk',
    'gov.uk',
    'com.au',
    'net.au',
    'org.au',
    'edu.au',
    'gov.au',
    'id.au',
    'co.nz',
    'net.nz',
    'org.nz',
    'govt.nz',
    'com.br',
    'net.br',
    'org.br',
    'gov.br',
    'com.mx',
    'com.ar',
    'com.co',
    'com.pe',
    'com.ve',
    'com.uy',
    'co.jp',
    'ne.jp',
    'or.jp',
    'ac.jp',
    'go.jp',
    'co.kr',
    'or.kr',
    'go.kr',
    'com.cn',
    'net.cn',
    'org.cn',
    'gov.cn',
    'com.tw',
    'com.hk',
    'com.sg',
    'com.my',
    'com.ph',
    'co.th',
    'co.id',
    'co.in',
    'net.in',
    'org.in',
    'co.za',
    'org.za',
    'com.tr',
    'com.ua',
    'com.pl',
    'com.es',
    'com.pt',
    'com.gr',
    'co.il',
    'com.sa',
    'com.eg',
    'com.ng',
    'co.ke',
]);

export function hostOf(value: string): string | null {
    const trimmed = value.trim();
    if (trimmed === '') return null;

    try {
        const url = new URL(/^[a-z][a-z0-9+.-]*:\/\//i.test(trimmed) ? trimmed : `https://${trimmed}`);

        return url.protocol === 'http:' || url.protocol === 'https:' ? url.hostname.toLowerCase() : null;
    } catch {
        return null;
    }
}

export function registrable(host: string): string | null {
    const labels = host.replace(/^\.+|\.+$/g, '').split('.');
    if (labels.length < 2) return null;

    const lastTwo = labels.slice(-2).join('.');

    if (MULTI_LABEL.has(lastTwo)) {
        return labels.length >= 3 ? labels.slice(-3).join('.') : null;
    }

    return lastTwo;
}

export function sameSite(a: string, b: string): boolean {
    const left = registrable(a);

    return left !== null && left === registrable(b);
}

/** Plain-text length of the brief, so markup is not charged to the counter. */
export function taskLength(html: string): number {
    const el = document.createElement('div');
    el.innerHTML = html;

    return (el.textContent ?? '').trim().length;
}

/**
 * What is still missing on a step.
 *
 * Returned as a list of sentences rather than a boolean so the disabled Next
 * button can say what it is waiting for. A disabled control with no explanation
 * is the worst thing in a form: it does not move and does not say why.
 */
export function missingFor(step: number, state: WizardState): string[] {
    if (step === 1) return missingStep1(state);
    if (step === 3) return missingStep3(state);

    // Step 2 is entirely optional. Targeting narrows the catalog; leaving it
    // blank means "show me everything", which is a legitimate answer.
    return taskLength(state.publisher_task) > MAX_TASK_CHARS
        ? [`The brief is over ${MAX_TASK_CHARS.toLocaleString('en-US')} characters.`]
        : [];
}

function missingStep1(state: WizardState): string[] {
    const missing: string[] = [];

    if (state.website_url.trim() === '') missing.push('The website you are promoting.');
    else if (hostOf(state.website_url) === null) missing.push('A valid website address.');

    if (state.name.trim() === '') missing.push('A project name.');
    else if (state.name.length > 60) missing.push('A project name of 60 characters or fewer.');

    if (state.category_id === '') missing.push('A category.');

    return missing;
}

function missingStep3(state: WizardState): string[] {
    const missing: string[] = [];
    const rows = state.landing_pages;
    const filled = rows.filter((row) => row.anchor_text.trim() !== '' || row.url.trim() !== '');

    if (filled.length === 0) {
        return ['At least one landing page with anchor text and a URL.'];
    }

    if (filled.some((row) => row.anchor_text.trim() === '')) missing.push('Anchor text on every row.');
    if (filled.some((row) => row.url.trim() === '')) missing.push('A target URL on every row.');

    const promoted = hostOf(state.website_url);

    if (promoted !== null) {
        const offSite = filled.filter((row) => {
            const host = hostOf(row.url);

            return row.url.trim() !== '' && (host === null || !sameSite(host, promoted));
        });

        if (offSite.length > 0) {
            missing.push(
                `Every URL on ${registrable(promoted) ?? promoted} — ${offSite.length} ${offSite.length === 1 ? 'is' : 'are'} on another site.`,
            );
        }
    }

    return missing;
}

/**
 * Per-row landing page errors, keyed by row index.
 *
 * @returns index → message
 */
export function landingPageErrors(state: LandingPageContext): Record<number, string> {
    const promoted = hostOf(state.website_url);
    const errors: Record<number, string> = {};

    state.landing_pages.forEach((row, index) => {
        if (row.url.trim() === '') return;

        const host = hostOf(row.url);

        if (host === null) {
            errors[index] = 'That does not look like a web address.';

            return;
        }

        if (promoted !== null && !sameSite(host, promoted)) {
            errors[index] =
                `This has to be a page on ${registrable(promoted) ?? promoted} — you entered ${host}. ` +
                'Landing pages are the pages on your own site that the links point to.';
        }
    });

    return errors;
}

/**
 * Commercial-intent anchors as a share of the total.
 *
 * The heuristic is intentionally crude — it looks for money words and for
 * anchors that are not the brand — because the output is advisory. Getting it
 * slightly wrong costs a sentence of advice nobody has to take; blocking on it
 * would cost someone their afternoon.
 */
const COMMERCIAL =
    /\b(buy|best|top|cheap|price|pricing|deal|discount|coupon|review|reviews|compare|vs|software|tool|service|company|agency|near me|for sale|order|shop)\b/i;

export function anchorHealth(state: LandingPageContext): { total: number; commercial: number; share: number } {
    const anchors = state.landing_pages.map((row) => row.anchor_text.trim()).filter((anchor) => anchor !== '');

    const brand =
        registrable(hostOf(state.website_url) ?? '')
            ?.split('.')[0]
            ?.toLowerCase() ?? null;

    const commercial = anchors.filter((anchor) => {
        const lower = anchor.toLowerCase();

        // A branded anchor is never counted as exact-match commercial, even if
        // it happens to contain one of the words above.
        if (brand !== null && brand.length > 2 && lower.includes(brand)) return false;

        // Generic anchors ("click here", "this page", a bare URL) are the
        // opposite of exact-match and are not counted either.
        if (/^(click here|here|this page|read more|learn more|website|link)$/i.test(lower)) return false;
        if (/^https?:\/\//i.test(lower)) return false;

        return COMMERCIAL.test(lower) || anchor.split(/\s+/).length >= 2;
    }).length;

    return {
        total: anchors.length,
        commercial,
        share: anchors.length === 0 ? 0 : commercial / anchors.length,
    };
}

/** Parses `anchor | url` lines, reporting per-line problems. */
export function parseBulk(text: string): {
    rows: { anchor_text: string; url: string }[];
    errors: { line: number; text: string; reason: string }[];
} {
    const rows: { anchor_text: string; url: string }[] = [];
    const errors: { line: number; text: string; reason: string }[] = [];

    text.split(/\r?\n/).forEach((raw, index) => {
        const line = raw.trim();
        if (line === '') return;

        const parts = line.split('|');

        if (parts.length < 2) {
            errors.push({ line: index + 1, text: line, reason: 'No “|” — write it as anchor text | URL.' });

            return;
        }

        const anchor = parts[0]!.trim();
        const url = parts.slice(1).join('|').trim();

        if (anchor === '') {
            errors.push({ line: index + 1, text: line, reason: 'Missing anchor text before the “|”.' });

            return;
        }

        if (anchor.length > 120) {
            errors.push({ line: index + 1, text: line, reason: 'Anchor text is over 120 characters.' });

            return;
        }

        if (url === '' || hostOf(url) === null) {
            errors.push({ line: index + 1, text: line, reason: 'Missing or unreadable URL after the “|”.' });

            return;
        }

        rows.push({ anchor_text: anchor, url });
    });

    return { rows, errors };
}
