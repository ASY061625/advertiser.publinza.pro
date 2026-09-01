#!/usr/bin/env node
/**
 * Asserts the three surfaces do not share page code.
 *
 * Walks each entry point's full import graph in the Vite manifest and fails if
 * any module belonging to another surface is reachable. `resources/js/shared`
 * is deliberately reachable from all three — that is what it is for.
 */
import { readFileSync } from 'node:fs';

// Marketing is Blade plus a vanilla island; the other two are Inertia bundles.
const SURFACES = ['marketing', 'advertiser', 'admin'];
const ENTRY = { marketing: 'main.ts', advertiser: 'main.tsx', admin: 'main.tsx' };
const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));

function reachable(entry) {
    const seen = new Set();
    const stack = [entry];

    while (stack.length > 0) {
        const key = stack.pop();
        if (seen.has(key)) continue;
        seen.add(key);

        const node = manifest[key];
        if (!node) continue;

        stack.push(...(node.imports ?? []), ...(node.dynamicImports ?? []));
    }

    return seen;
}

let failed = false;

for (const surface of SURFACES) {
    const entry = `resources/js/${surface}/${ENTRY[surface]}`;

    if (!manifest[entry]) {
        console.error(`✗ ${surface}: entry ${entry} is missing from the manifest.`);
        failed = true;
        continue;
    }

    const leaks = [...reachable(entry)].filter((key) =>
        SURFACES.some((other) => other !== surface && key.startsWith(`resources/js/${other}/`)),
    );

    if (leaks.length > 0) {
        console.error(`✗ ${surface} bundle reaches another surface:\n  ${leaks.join('\n  ')}`);
        failed = true;
    } else {
        console.log(`✓ ${surface} bundle is isolated`);
    }
}

process.exit(failed ? 1 : 0);
