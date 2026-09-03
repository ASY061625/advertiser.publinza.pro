export interface DiffPart {
    value: string;
    kind: 'same' | 'added' | 'removed';
}

/**
 * A word-level diff of two pieces of prose.
 *
 * Word-level rather than character-level: the publisher brief is sentences, and
 * a character diff of a rewritten sentence produces confetti — a dozen tiny
 * insertions inside words nobody edited.
 *
 * The algorithm is a longest-common-subsequence over tokens. It is O(n·m) in
 * the token counts, which is fine for a 3,000-character brief and is why the
 * inputs are capped before it runs.
 */
const MAX_TOKENS = 1200;

export function diffWords(before: string, after: string): DiffPart[] {
    const a = tokenize(before).slice(0, MAX_TOKENS);
    const b = tokenize(after).slice(0, MAX_TOKENS);

    // The LCS table. Row i, column j is the length of the longest common
    // subsequence of a[i..] and b[j..].
    const lcs: number[][] = Array.from({ length: a.length + 1 }, () => new Array<number>(b.length + 1).fill(0));

    for (let i = a.length - 1; i >= 0; i--) {
        for (let j = b.length - 1; j >= 0; j--) {
            lcs[i]![j] = a[i] === b[j] ? lcs[i + 1]![j + 1]! + 1 : Math.max(lcs[i + 1]![j]!, lcs[i]![j + 1]!);
        }
    }

    const parts: DiffPart[] = [];
    let i = 0;
    let j = 0;

    const push = (value: string, kind: DiffPart['kind']) => {
        const last = parts[parts.length - 1];

        // Runs are merged as they are produced, so a paragraph of additions is
        // one span rather than forty.
        if (last && last.kind === kind) last.value += value;
        else parts.push({ value, kind });
    };

    while (i < a.length && j < b.length) {
        if (a[i] === b[j]) {
            push(a[i]!, 'same');
            i++;
            j++;
        } else if (lcs[i + 1]![j]! >= lcs[i]![j + 1]!) {
            push(a[i]!, 'removed');
            i++;
        } else {
            push(b[j]!, 'added');
            j++;
        }
    }

    while (i < a.length) push(a[i++]!, 'removed');
    while (j < b.length) push(b[j++]!, 'added');

    return parts;
}

/**
 * Words, whitespace, and each punctuation mark on its own, so rejoining is
 * lossless and a comma is not part of the word in front of it.
 *
 * That last part is the whole reason this is not `/\s+|\S+/`: with punctuation
 * glued on, adding a comma after "plain" reads as deleting the word "plain"
 * and inserting the word "plain," — the diff strikes through a word nobody
 * touched and prints it twice, which is exactly what a reader cannot parse.
 */
function tokenize(value: string): string[] {
    return value.match(/\s+|[\p{L}\p{N}]+(?:['’-][\p{L}\p{N}]+)*|[^\s\p{L}\p{N}]/gu) ?? [];
}
