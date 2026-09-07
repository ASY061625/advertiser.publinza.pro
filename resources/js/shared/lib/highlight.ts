/** A run of text, and whether the query matched it. */
export interface Segment {
    text: string;
    match: boolean;
}

/**
 * Splits a string into matched and unmatched runs.
 *
 * Done in the browser rather than taken from the search engine, for two
 * reasons. Meilisearch's `_formatted` returns HTML, and rendering
 * engine-supplied markup around a domain somebody else registered means
 * `dangerouslySetInnerHTML` over untrusted text; and `_matchesPosition` returns
 * *byte* offsets, which do not line up with JavaScript string indices the
 * moment a title contains a non-ASCII character.
 *
 * The trade is that a typo-tolerant hit — Meilisearch matching "techweekly" for
 * "techweekli" — comes back with nothing highlighted, because there is no
 * matched substring to mark. The row is still there and still correct; it is
 * just not underlined. That is a better failure than either of the above.
 */
export function segments(text: string, query: string): Segment[] {
    const haystack = text ?? '';

    // Every whitespace-separated term, longest first: "tech we" against
    // "techweekly" should mark the longer run rather than letting the shorter
    // one claim a prefix of it.
    const terms = query
        .toLowerCase()
        .split(/\s+/)
        .map((term) => term.trim())
        .filter((term) => term.length > 0)
        .sort((a, b) => b.length - a.length);

    if (haystack === '' || terms.length === 0) {
        return [{ text: haystack, match: false }];
    }

    const lower = haystack.toLowerCase();

    // A flag per character, so overlapping matches from different terms merge
    // into one run instead of producing nested or duplicated segments.
    const marked = new Array<boolean>(haystack.length).fill(false);

    for (const term of terms) {
        let from = 0;

        for (;;) {
            const at = lower.indexOf(term, from);

            if (at === -1) break;

            for (let i = at; i < at + term.length; i++) marked[i] = true;

            from = at + term.length;
        }
    }

    const out: Segment[] = [];

    for (let i = 0; i < haystack.length; i++) {
        const character = haystack.charAt(i);
        const isMatch = marked[i] === true;
        const last = out[out.length - 1];

        if (last !== undefined && last.match === isMatch) {
            last.text += character;
        } else {
            out.push({ text: character, match: isMatch });
        }
    }

    return out.length === 0 ? [{ text: haystack, match: false }] : out;
}

/**
 * Fuzzy subsequence match, for the Actions list.
 *
 * "tpb" matches "Top up balance": every character of the query appears in
 * order, not necessarily adjacent. Deliberately not applied to search results —
 * a subsequence match against a catalog of ten thousand domains matches almost
 * everything — but it is exactly right for a fixed list of eight commands.
 */
export function fuzzyMatches(text: string, query: string): boolean {
    const needle = query.toLowerCase().replace(/\s+/g, '');

    if (needle === '') return true;

    const haystack = text.toLowerCase();
    let at = 0;

    for (const character of needle) {
        at = haystack.indexOf(character, at);

        if (at === -1) return false;

        at++;
    }

    return true;
}
