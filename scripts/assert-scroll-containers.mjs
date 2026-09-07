#!/usr/bin/env node
/**
 * Fails the build when a horizontal scroll container is not positioned.
 *
 * `sr-only` is `position: absolute`. Inside an `overflow-x-auto` container with
 * no positioned ancestor, an `sr-only` caption or column label resolves against
 * the initial containing block instead of the container — so a 1px span lands
 * hundreds of pixels right of the viewport and gives the whole *page* a
 * horizontal scrollbar.
 *
 * What makes it worth a build check rather than a code review note is how it
 * fails. The table still scrolls correctly. Nothing looks wrong. Nothing throws.
 * The only symptom is that the document is wider than the window, which nobody
 * notices on a desktop and everybody notices on a phone. It shipped four times
 * in this codebase before anyone measured `document.scrollWidth`.
 *
 * The fix is always the same word: `relative` on the scroll container.
 */
import { readFileSync, readdirSync } from 'node:fs';
import path from 'node:path';

const ROOT = 'resources/js';

/** Anything that establishes a containing block for an absolute descendant. */
const POSITIONED = /\b(relative|absolute|fixed|sticky)\b/;

/**
 * A className string containing a horizontal scroll utility.
 *
 * Matched on the whole attribute rather than line-by-line, because `cn(...)`
 * calls and conditional classes routinely wrap across several lines.
 */
const SCROLLER = /\boverflow-x-(auto|scroll)\b/;

function walk(dir) {
    const out = [];

    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);

        if (entry.isDirectory()) out.push(...walk(full));
        else if (/\.tsx?$/.test(entry.name)) out.push(full);
    }

    return out;
}

const failures = [];

for (const file of walk(ROOT)) {
    const source = readFileSync(file, 'utf8');

    // Every className={...} or className="..." payload, however it is spelled.
    const attributes = source.matchAll(/className=(?:\{([\s\S]*?)\}|"([^"]*)")/g);

    for (const match of attributes) {
        const value = match[1] ?? match[2] ?? '';

        if (!SCROLLER.test(value) || POSITIONED.test(value)) continue;

        const line = source.slice(0, match.index).split('\n').length;

        failures.push({ file, line, value: value.replace(/\s+/g, ' ').trim().slice(0, 76) });
    }
}

if (failures.length > 0) {
    console.error('✗ Horizontal scroll containers that are not positioned:\n');

    for (const f of failures) {
        console.error(`  ${f.file}:${f.line}`);
        console.error(`    ${f.value}`);
    }

    console.error(
        '\n  Add `relative`. Without it an `sr-only` label inside escapes the container' +
        '\n  and gives the whole page a horizontal scrollbar — see the comment in' +
        '\n  resources/js/shared/ui/Table.tsx.\n',
    );

    process.exit(1);
}

console.log('✓ Every horizontal scroll container is positioned.');
