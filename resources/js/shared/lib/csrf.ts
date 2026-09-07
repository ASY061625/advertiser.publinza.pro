/**
 * CSRF headers for a hand-rolled `fetch`.
 *
 * Inertia's own requests go through axios, which reads the `XSRF-TOKEN` cookie
 * on every call. A `fetch` written by hand does not, and the obvious substitute
 * — the `<meta name="csrf-token">` tag — is a trap in a single-page app:
 *
 *   The meta tag is rendered once, into the document that first loaded. Signing
 *   in regenerates the session and its token, but the *document* is never
 *   re-rendered, because Inertia turns the login redirect into a client-side
 *   visit. From that moment the meta tag holds a token from before the session
 *   existed, and every fetch carrying it gets a 419.
 *
 * The cookie has no such problem: Laravel re-sets it on every response, so it
 * is current by construction. It is read first here, with the meta tag kept
 * only as a fallback for a first render where the cookie has not landed yet.
 */
export function csrfHeaders(): Record<string, string> {
    const cookie = readCookie('XSRF-TOKEN');

    if (cookie !== null) {
        // X-XSRF-TOKEN, not X-CSRF-TOKEN: the cookie value is encrypted, and
        // Laravel only decrypts it for that header.
        return { 'X-XSRF-TOKEN': cookie };
    }

    const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

    return meta === undefined ? {} : { 'X-CSRF-TOKEN': meta };
}

/** The usual JSON write headers, CSRF included. */
export function jsonHeaders(): Record<string, string> {
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...csrfHeaders(),
    };
}

/**
 * The plaintext session token, for a hidden `_token` field on a real form.
 *
 * The cookie cannot serve here: its value is encrypted, and Laravel only
 * decrypts it for the X-XSRF-TOKEN header. A form field is compared against the
 * session token directly, so the meta tag is the only source — which is safe
 * because main.tsx rewrites that tag from a shared prop on every response.
 */
export function sessionToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

function readCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${name}=([^;]*)`));
    const value = match?.[1];

    if (value === undefined || value === '') {
        return null;
    }

    try {
        return decodeURIComponent(value);
    } catch {
        // A malformed cookie is not worth throwing over; the meta tag stands in.
        return null;
    }
}
