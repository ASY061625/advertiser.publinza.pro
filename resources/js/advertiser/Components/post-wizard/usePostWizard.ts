import { useCallback, useEffect, useRef, useState } from 'react';
import type { PostWizardState } from '@shared/types/postWizard';

/** Ten seconds, as specified: invisible to type through, short enough to matter. */
const AUTOSAVE_MS = 10_000;

export function blankState(projectId: number | null): PostWizardState {
    return {
        projectId: projectId === null ? '' : String(projectId),
        folderId: '',
        landingPageId: '',
        anchorText: '',
        targetUrl: '',
        websiteId: '',
        websiteSlug: '',
        serviceType: 'article_placement',
        express: false,
        contentMode: 'advertiser_provides',
        title: '',
        body: '',
        brief: '',
        keywords: '',
        tone: '',
        targetWords: '',
        search: '',
        categoryId: '',
        price: '',
        traffic: '',
        dr: '',
    };
}

interface Options {
    /** Labels for the draft card, so it can say what the draft is about. */
    describe: () => { projectName: string | null; websiteDomain: string | null };
}

/**
 * The wizard's state, its step, and the autosave behind both.
 *
 * One state object rather than per-step state is what makes "back never loses
 * data" true by construction: the steps render from this and own nothing, so
 * going back changes which step is visible and nothing else.
 *
 * Autosave is a ten-second interval rather than a debounce on every keystroke.
 * The draft is insurance against a closed tab, not a collaborative document —
 * a request behind every character of a 5,000-word brief buys a guarantee
 * nobody needs that finely.
 *
 * It is also fire-and-forget. A failed save never interrupts: telling somebody
 * mid-sentence that their insurance hiccuped costs more than the insurance is
 * worth, and the next tick tries again anyway.
 */
export function usePostWizard(initial: PostWizardState, initialStep: number, { describe }: Options) {
    const [state, setState] = useState(initial);
    const [step, setStep] = useState(initialStep);
    const [savedAt, setSavedAt] = useState<string | null>(null);

    // Nothing is saved until something is edited, so opening the wizard and
    // closing it again does not strand a draft to resume.
    const dirty = useRef(false);
    const latest = useRef({ state, step, describe });
    latest.current = { state, step, describe };

    const patch = useCallback((changes: Partial<PostWizardState>) => {
        dirty.current = true;
        setState((current) => ({ ...current, ...changes }));
    }, []);

    const save = useCallback(async () => {
        if (!dirty.current) return;

        const { state: current, step: at, describe: label } = latest.current;
        const { projectName, websiteDomain } = label();

        try {
            const response = await fetch('/posts/wizard/draft', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    step: at,
                    // The two labels ride along so the dashboard card can name
                    // the draft without the server re-resolving ids on every
                    // dashboard load for a card that is usually absent.
                    payload: { ...current, project_name: projectName, website_domain: websiteDomain },
                }),
            });

            const body: unknown = response.ok ? await response.json() : null;
            const stamp = (body as { saved_at?: string } | null)?.saved_at;

            if (typeof stamp === 'string') setSavedAt(stamp);
        } catch {
            // Deliberately silent. The next tick tries again.
        }
    }, []);

    useEffect(() => {
        const timer = window.setInterval(() => void save(), AUTOSAVE_MS);

        return () => window.clearInterval(timer);
    }, [save]);

    const goTo = useCallback((next: number) => setStep(next), []);

    // Called once the wizard's answers have become a real cart line. Without
    // it the interval keeps running against state that has already been
    // spent, and ten seconds later it writes back the draft the server just
    // deleted — leaving the dashboard offering to resume a post already bought.
    const markSpent = useCallback(() => {
        dirty.current = false;
    }, []);

    return { state, patch, step, goTo, savedAt, save, markSpent, isDirty: () => dirty.current };
}

/** Rebuilds state from a stored draft, which is JSON the client last wrote. */
export function fromDraft(payload: Record<string, unknown>, projectId: number | null): PostWizardState {
    const base = blankState(projectId);
    const merged: PostWizardState = { ...base };

    for (const key of Object.keys(base) as (keyof PostWizardState)[]) {
        const value = payload[key];

        if (key === 'express') {
            merged.express = value === true;
        } else if (key === 'contentMode') {
            merged.contentMode = value === 'publisher_writes' ? 'publisher_writes' : 'advertiser_provides';
        } else if (typeof value === 'string') {
            merged[key] = value;
        } else if (typeof value === 'number' && Number.isFinite(value)) {
            merged[key] = String(value);
        }
    }

    // A draft opened from inside a project keeps that project rather than the
    // one it was saved under: the launcher's context is the newer intent.
    return projectId === null ? merged : { ...merged, projectId: String(projectId) };
}
