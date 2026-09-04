/**
 * The colours the three comparison charts are drawn in.
 *
 * Validated with the dataviz palette checker against a white surface, all pairs
 * — not chosen by eye. The full set is brand blue plus the three competitor
 * hues below:
 *
 *   lightness band   PASS  all inside L 0.43–0.77
 *   chroma floor     PASS  all >= 0.1
 *   CVD separation   WARN  worst pair #a21caf x #1d4ed8, ΔE 7.4 (protan)
 *   normal vision    PASS  worst pair ΔE 22.2
 *   contrast         PASS  all >= 3:1 on white
 *
 * That WARN sits in the 6–8 band, which is legal only with a second encoding
 * carrying the same identity. There are three here: the stroke pattern below,
 * the legend swatch that draws each series exactly as the chart does, and the
 * table view under every chart. The pair it applies to is brand blue against
 * fuchsia — your own site against the fourth competitor — which is also the
 * one pair the reader has the most other help telling apart, since your site
 * is the thick solid line and the only one in the card above the table.
 */

/** Your site, everywhere it appears. Never assigned to a competitor. */
export const SELF_COLOR = '#1d4ed8';

/**
 * Three hues and four stroke patterns give ten stable identities, which is the
 * per-project limit. Cycling a hue on its own would give the fourth competitor
 * the first one's colour; pairing it with a stroke keeps every row distinct
 * without inventing a hue that fails the checks above.
 */
const HUES = ['#0d9488', '#c2410c', '#a21caf'];

const DASHES: (string | undefined)[] = [undefined, '7 4', '2 3', '9 3 2 3'];

export interface SeriesStroke {
    color: string;
    /** An SVG stroke-dasharray, or undefined for a solid line. */
    dash?: string;
    width: number;
}

/**
 * The stroke for one series.
 *
 * `slot` is the competitor's position among the tracked rows — assigned when it
 * was added and never recomputed — so sorting the table or hiding a line cannot
 * repaint the ones that remain. Colour follows the entity, not its rank.
 */
export function strokeFor(slot: number | null): SeriesStroke {
    if (slot === null) {
        // Thicker as well as brand-coloured: your site is the line every other
        // line is being read against, so it should be findable without the key.
        return { color: SELF_COLOR, width: 2.5 };
    }

    return {
        color: HUES[slot % HUES.length] ?? HUES[0]!,
        dash: DASHES[Math.floor(slot / HUES.length) % DASHES.length],
        width: 1.75,
    };
}

/** The two measures of the authority chart. Two hues, one axis, both 0–100. */
export const AUTHORITY_COLORS = { dr: '#0d9488', da: '#c2410c' } as const;

/**
 * The keyword-overlap segments.
 *
 * Blue is you and orange is them, matching how the trend chart reads. The
 * shared segment is deliberately neutral: it belongs to neither side, and a
 * third hue there would compete with the two that carry the comparison.
 */
export const OVERLAP_COLORS = {
    yours: SELF_COLOR,
    shared: '#94a3b8',
    theirs: '#c2410c',
} as const;
