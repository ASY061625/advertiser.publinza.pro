import { useCallback, useEffect, useRef, useState } from 'react';
import type { LandingPageRow, WizardState } from '@shared/types/wizard';

export const EMPTY_ROW = (): LandingPageRow => ({
    key: `row-${Math.random().toString(36).slice(2, 10)}`,
    anchor_text: '',
    url: '',
});

export function blankState(defaultColor: string): WizardState {
    return {
        website_url: '',
        name: '',
        category_id: '',
        color: defaultColor,
        sensitive_topic_ids: [],
        country_ids: [],
        language_ids: [],
        publisher_task: '',
        landing_pages: [EMPTY_ROW()],
        preview: null,
    };
}

/**
 * The wizard's state, and its autosave.
 *
 * Back navigation preserves everything because there is only one state object:
 * the steps render from it rather than owning fields of their own, so going
 * back changes which step is visible and nothing else.
 *
 * Autosave is debounced and fire-and-forget. It never blocks a keystroke and
 * never surfaces a failure mid-sentence — the draft is insurance against a
 * closed tab, and interrupting someone to say the insurance hiccuped costs
 * more than the insurance is worth.
 */
export function useProjectWizard(initial: WizardState, initialStep: number) {
    const [state, setState] = useState(initial);
    const [step, setStep] = useState(initialStep);
    const [savedAt, setSavedAt] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    // Nothing is saved until something is edited, so opening the wizard and
    // leaving again does not strand a draft to resume.
    const dirty = useRef(false);
    const latest = useRef({ state, step });
    latest.current = { state, step };

    const patch = useCallback((changes: Partial<WizardState>) => {
        dirty.current = true;
        setState((current) => ({ ...current, ...changes }));
    }, []);

    const goTo = useCallback((next: number) => {
        setStep(next);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, []);

    useEffect(() => {
        if (!dirty.current) return;

        const timer = window.setTimeout(() => {
            setSaving(true);

            void fetch('/projects/draft', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    step: latest.current.step,
                    payload: toPayload(latest.current.state),
                }),
            })
                .then((response) => (response.ok ? response.json() : null))
                .then((body: { saved_at?: string } | null) => setSavedAt(body?.saved_at ?? null))
                .catch(() => undefined)
                .finally(() => setSaving(false));
        }, 1200);

        return () => window.clearTimeout(timer);
    }, [state, step]);

    return { state, patch, step, goTo, savedAt, saving };
}

/** The shape the server stores. Row keys are a client concern and dropped. */
export function toPayload(state: WizardState): Record<string, unknown> {
    return {
        website_url: state.website_url,
        name: state.name,
        category_id: state.category_id === '' ? null : Number(state.category_id),
        color: state.color,
        sensitive_topic_ids: state.sensitive_topic_ids,
        country_ids: state.country_ids,
        language_ids: state.language_ids,
        publisher_task: state.publisher_task,
        landing_pages: state.landing_pages.map((row) => ({
            anchor_text: row.anchor_text,
            url: row.url,
        })),
        preview: state.preview,
    };
}

/**
 * Rebuilds wizard state from a stored draft, restoring the row keys the
 * payload does not carry.
 */
export function fromPayload(payload: Record<string, unknown>, defaultColor: string): WizardState {
    const base = blankState(defaultColor);
    const rows = Array.isArray(payload.landing_pages) ? payload.landing_pages : [];

    return {
        ...base,
        website_url: text(payload.website_url),
        name: text(payload.name),
        category_id: text(payload.category_id),
        color: typeof payload.color === 'string' ? payload.color : base.color,
        sensitive_topic_ids: numbers(payload.sensitive_topic_ids),
        country_ids: numbers(payload.country_ids),
        language_ids: numbers(payload.language_ids),
        publisher_task: text(payload.publisher_task),
        landing_pages:
            rows.length === 0
                ? [EMPTY_ROW()]
                : rows.map((row) => ({
                      ...EMPTY_ROW(),
                      anchor_text: text((row as Record<string, unknown>).anchor_text),
                      url: text((row as Record<string, unknown>).url),
                  })),
        preview: (payload.preview as WizardState['preview']) ?? null,
    };
}

function numbers(value: unknown): number[] {
    return Array.isArray(value) ? value.map(Number).filter((n) => Number.isFinite(n) && n > 0) : [];
}

/**
 * A draft payload is JSON from the server, but it is JSON the client last
 * wrote, so it is not above suspicion. Anything that is not already a string or
 * a number becomes empty rather than the string "[object Object]".
 */
function text(value: unknown): string {
    if (typeof value === 'string') return value;

    return typeof value === 'number' && Number.isFinite(value) ? String(value) : '';
}
