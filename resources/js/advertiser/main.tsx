import '../../css/globals.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ToastProvider } from '@shared/ui';
import { configureFormats, type FormatConfig } from '@shared/lib/format';
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
        /*
         * Dates and numbers before the first paint.
         *
         * @shared/lib/format holds its configuration as module state, so this
         * has to run before anything renders — otherwise the first frame is in
         * the default format and the second is in the reader's, which is a
         * visible flicker on every table in the app.
         *
         * Re-applied on every navigation because saving the preference is an
         * ordinary Inertia visit: the new value arrives in the shared props of
         * the very response that confirms the save.
         */
        const apply = (page: { props: Record<string, unknown> }) => {
            const formats = page.props.formats as Partial<FormatConfig> | undefined;

            if (formats) configureFormats(formats);

            /*
             * And the CSRF token, for the same reason.
             *
             * The meta tag is written once into the document that first loaded.
             * Signing in regenerates the session token and Inertia turns the
             * redirect into a client-side visit, so the tag is left holding a
             * token from before the session existed — and every hand-rolled
             * fetch and hidden _token field carrying it gets a 419. Rewriting
             * it from the shared prop keeps it true after every response.
             */
            const token = page.props.csrfToken;
            const tag = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');

            if (typeof token === 'string' && tag !== null) tag.content = token;
        };

        apply(props.initialPage);
        router.on('success', (event) => apply(event.detail.page as { props: Record<string, unknown> }));

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
