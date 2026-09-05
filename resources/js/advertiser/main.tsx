import '../../css/globals.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ToastProvider } from '@shared/ui';
import { AddPostProvider } from './Components/post-wizard/AddPostProvider';

/**
 * advertiser entry point.
 *
 * The glob below is rooted in this directory only. Nothing outside
 * `resources/js/advertiser` and `resources/js/shared` can enter this bundle.
 */
const pages = import.meta.glob('./Pages/**/*.tsx');

void createInertiaApp({
    title: (title) => (title ? `${title} · Publinza` : 'Publinza'),
    resolve: (name) => {
        const page = pages[`./Pages/${name}.tsx`];

        if (!page) {
            throw new Error(`Inertia page "${name}" is not part of the advertiser bundle.`);
        }

        return page();
    },
    setup({ el, App, props }) {
        // Above the page, not inside AppShell. Every page renders its own
        // <AppShell>, so a provider in there is a *descendant* of the page
        // component — a page calling useToast() would throw, which is exactly
        // what /posts did until this moved up here.
        // The add-post wizard is mounted once, above every page, so the
        // sidebar's quick action opens the same modal the dashboard and the
        // post manager do — and so a page calling useAddPost() is inside the
        // provider rather than a parent of it.
        createRoot(el).render(
            <ToastProvider>
                <AddPostProvider>
                    <App {...props} />
                </AddPostProvider>
            </ToastProvider>,
        );
    },
    progress: { color: '#1D4ED8' },
});
