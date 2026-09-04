/**
 * A country's flag, from its ISO 3166-1 alpha-2 code.
 *
 * Built from regional indicator symbols rather than fetched as an image: the
 * catalog shows a flag on every row and in a list of two hundred countries, and
 * that is either two hundred requests to somewhere or a sprite sheet to keep in
 * step with the country table. The glyph is already in the font stack.
 */
export function flagFor(code: string): string {
    const upper = code.trim().toUpperCase();

    if (!/^[A-Z]{2}$/.test(upper)) return '';

    // 0x1F1E6 is REGIONAL INDICATOR SYMBOL LETTER A, 65 codepoints past "A".
    return String.fromCodePoint(...[...upper].map((letter) => letter.charCodeAt(0) + 0x1f1e6 - 65));
}
