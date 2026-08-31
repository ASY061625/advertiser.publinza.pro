import '../../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

/**
 * admin entry point.
 *
 * The glob below is rooted in this directory only. Nothing outside
 * `resources/js/admin` and `resources/js/shared` can enter this bundle.
 */
const pages = import.meta.glob('./Pages/**/*.tsx');

void createInertiaApp({
    title: (title) => (title ? `${title} · Publinza admin` : 'Publinza admin'),
    resolve: (name) => {
        const page = pages[`./Pages/${name}.tsx`];

        if (!page) {
            throw new Error(`Inertia page "${name}" is not part of the admin bundle.`);
        }

        return page();
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#0B1B33' },
});
