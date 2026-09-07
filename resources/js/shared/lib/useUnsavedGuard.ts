import { useEffect } from 'react';
import { router } from '@inertiajs/react';

/**
 * Stops somebody navigating away from a form they have edited and not saved.
 *
 * Two escapes have to be covered and they are covered differently, because the
 * browser will not let a page script block a tab close with a custom question:
 *
 *  - Leaving the app entirely (close, reload, external link) gets the browser's
 *    own dialog via `beforeunload`. The wording is the browser's; all a page
 *    can do is ask for it.
 *  - Moving to another page *inside* the app is an Inertia visit, which is
 *    ours to cancel — so that one gets a real question with real words.
 *
 * Deliberately not wired to the tab strip on the profile: switching tabs there
 * does not discard anything, because each tab holds its own form state for as
 * long as the page is mounted. Prompting to leave a tab that loses nothing
 * teaches people to dismiss the prompt that matters.
 */
export function useUnsavedGuard(dirty: boolean, message = 'You have unsaved changes. Leave without saving?') {
    useEffect(() => {
        if (!dirty) return;

        const onBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            // Assigning returnValue is what actually triggers the dialog in
            // Chrome; preventDefault alone is the newer spec and not universal.
            event.returnValue = '';
        };

        window.addEventListener('beforeunload', onBeforeUnload);

        const stop = router.on('before', (event) => {
            // A form POST from this very page is the save, not a departure.
            if (event.detail.visit.method !== 'get') return true;

            return window.confirm(message);
        });

        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            stop();
        };
    }, [dirty, message]);
}
