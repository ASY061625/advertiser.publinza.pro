const KEY = 'publinza.search.recent';
const KEEP = 10;

/**
 * The last ten things this person searched for.
 *
 * Local, not on the server, and that is the right call for a search history:
 * it is a browsing trace, it is only useful on the machine that made it, and
 * storing it per account would turn "what did I type" into a support-readable
 * record for no benefit to anyone.
 *
 * Every accessor is wrapped, because localStorage throws rather than returning
 * null in a private window and in browsers set to block site data — and a
 * palette that will not open because it could not read its own history is worse
 * than one with no history.
 */
export function readRecentSearches(): string[] {
    try {
        const raw = window.localStorage.getItem(KEY);

        if (raw === null) return [];

        const parsed: unknown = JSON.parse(raw);

        return Array.isArray(parsed)
            ? parsed.filter((entry): entry is string => typeof entry === 'string').slice(0, KEEP)
            : [];
    } catch {
        return [];
    }
}

/**
 * Adds one, newest first, without duplicates.
 *
 * Returns the new list rather than only writing it, so the caller can render
 * from the return value instead of reading storage back — which would be a
 * second chance to throw.
 */
export function pushRecentSearch(term: string): string[] {
    const trimmed = term.trim();

    if (trimmed.length < 2) return readRecentSearches();

    // Case-insensitive de-dupe, keeping the newest spelling: somebody who
    // searches "TechWeekly" after "techweekly" meant the same thing once.
    const next = [trimmed, ...readRecentSearches().filter((entry) => entry.toLowerCase() !== trimmed.toLowerCase())]
        .slice(0, KEEP);

    try {
        window.localStorage.setItem(KEY, JSON.stringify(next));
    } catch {
        // Full, or blocked. The list is still correct for this session.
    }

    return next;
}

export function clearRecentSearches(): string[] {
    try {
        window.localStorage.removeItem(KEY);
    } catch {
        // Nothing to do, and nothing worth telling anybody about.
    }

    return [];
}
