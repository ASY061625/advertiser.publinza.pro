#!/usr/bin/env node
/**
 * Fails the build when an Inertia page cannot render an `<h1>`.
 *
 * The shell is built on the assumption that every page names itself. The
 * breadcrumb drops its middle crumbs below `sm` on the reasoning — written down
 * in Header.tsx — that "the page's own h1 says where you are", and on a phone
 * the trail is hidden altogether. A page with no h1 therefore has nothing
 * naming it at exactly the width where that matters most, and assistive tech
 * has no top-level landmark to enter the content by.
 *
 * What makes it worth a build check rather than a review note is how it fails.
 * Nothing throws. Nothing looks wrong on a desktop, where the breadcrumb is
 * visible and doing the job. The page looks finished. Catalog, cart and
 * checkout each shipped this way and were found by measuring the DOM, not by
 * reading the diff — and catalog is the most-visited screen in the product.
 *
 * A page satisfies the rule three ways, because all three really are how pages
 * in this codebase get their heading:
 *
 *   1. It renders a literal `<h1>` — the common case, and the house pattern.
 *   2. It passes `heading="h1"` to a component that takes a heading level, the
 *      way Catalog/Website.tsx does with SiteHeader.
 *   3. It renders a component that provides the h1 — AuthLayout, ProjectLayout,
 *      ThreadList and AdminLayout all do.
 *
 * The third case is why this resolves imports and walks the component graph
 * rather than carrying a list of blessed filenames. An allowlist would have to
 * be edited every time a layout is added, renamed or moved, and the failure
 * mode of a stale allowlist is a page silently exempted from the check that
 * exists to protect it. Following the imports means the check keeps working
 * when the layouts change, and a page stops passing the moment it stops
 * actually rendering a heading.
 */
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import path from 'node:path';

const ROOT = 'resources/js';

/** tsconfig `paths`, which is how the shared surface is imported. */
const ALIASES = [
    ['@shared/', 'resources/js/shared/'],
    ['@/', 'resources/js/'],
];

const EXTENSIONS = ['.tsx', '.ts'];

function walk(dir) {
    const out = [];

    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);

        if (entry.isDirectory()) out.push(...walk(full));
        else if (/\.tsx?$/.test(entry.name)) out.push(full);
    }

    return out;
}

const sources = new Map();

function read(file) {
    if (!sources.has(file)) sources.set(file, readFileSync(file, 'utf8'));

    return sources.get(file);
}

/**
 * A module specifier to a file on disk, or null for anything outside the tree
 * (`react`, `@inertiajs/react`) — those can never be the source of our heading.
 */
function resolve(fromFile, specifier) {
    let base;

    if (specifier.startsWith('.')) {
        base = path.join(path.dirname(fromFile), specifier);
    } else {
        const alias = ALIASES.find(([prefix]) => specifier.startsWith(prefix));

        if (!alias) return null;

        base = specifier.replace(alias[0], alias[1]);
    }

    for (const extension of EXTENSIONS) {
        const candidate = base + extension;

        if (existsSync(candidate) && statSync(candidate).isFile()) return candidate;
    }

    // A directory import lands on its barrel, which re-exports the real file.
    for (const extension of EXTENSIONS) {
        const candidate = path.join(base, `index${extension}`);

        if (existsSync(candidate) && statSync(candidate).isFile()) return candidate;
    }

    return null;
}

/**
 * Local JSX name -> the file it came from.
 *
 * Names are resolved per file rather than globally because they collide:
 * `BulkBar`, `Section` and `DeltaChip` each name two different components in
 * this codebase, and a global map would answer with whichever was indexed last.
 */
function importedNames(file) {
    const source = read(file);
    const map = new Map();
    const pattern = /import\s+(?:type\s+)?([\s\S]*?)\s*from\s*['"]([^'"]+)['"]/g;

    for (const [, clause, specifier] of source.matchAll(pattern)) {
        const target = resolve(file, specifier);

        if (target === null) continue;

        // `Foo`, `{ A, B as C }`, `* as N`, and combinations of them.
        for (const binding of clause.replace(/[{}]/g, ',').split(',')) {
            const name = binding.trim().split(/\s+as\s+/).pop()?.trim();

            if (name && /^[A-Z][A-Za-z0-9_]*$/.test(name)) map.set(name, target);
        }
    }

    return map;
}

/** Every capitalised JSX tag rendered in a file, `<Foo.Bar>` counted as `Foo`. */
function renderedComponents(source) {
    return new Set([...source.matchAll(/<([A-Z][A-Za-z0-9_]*)/g)].map((match) => match[1]));
}

const HAS_H1 = /<h1[\s>]/;
const PASSES_H1 = /heading=["']h1["']/;

/**
 * Where this file's h1 comes from, or null if it has none.
 *
 * @returns {string|null} the file that actually renders the heading
 */
function headingSource(file, seen = new Set()) {
    if (seen.has(file)) return null;

    seen.add(file);

    const source = read(file);

    if (HAS_H1.test(source) || PASSES_H1.test(source)) return file;

    const imports = importedNames(file);

    for (const name of renderedComponents(source)) {
        const target = imports.get(name);

        if (target === undefined) continue;

        const found = headingSource(target, seen);

        if (found !== null) return found;
    }

    return null;
}

// --- Check every page on every surface ---------------------------------------

const pages = walk(ROOT)
    .filter((file) => /\/Pages\//.test(file) && file.endsWith('.tsx'))
    .sort();

if (pages.length === 0) {
    console.error(`✗ No Inertia pages found under ${ROOT}. Has the layout moved?`);
    process.exit(1);
}

const failures = pages.filter((page) => headingSource(page) === null);

if (failures.length > 0) {
    console.error('✗ Inertia pages that can never render an <h1>:\n');

    for (const file of failures) console.error(`  ${file}`);

    console.error(
        '\n  Give the page a heading:' +
        '\n' +
        '\n    <h1 className="font-sora text-xl font-semibold text-ink-900">Title</h1>' +
        '\n' +
        '\n  Put it outside any empty/loaded branch, or the page loses its name exactly' +
        '\n  when there is least else on screen to identify it. A page whose heading comes' +
        '\n  from a layout or from `heading="h1"` already passes — see the comment at the' +
        '\n  top of this file.\n',
    );

    process.exit(1);
}

console.log(`✓ All ${pages.length} pages render an <h1>.`);
