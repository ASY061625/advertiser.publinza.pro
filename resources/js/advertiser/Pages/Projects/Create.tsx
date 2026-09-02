import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { cn } from '@shared/lib/cn';
import { AppShell } from '../../Layouts/AppShell';
import { Button, ChevronLeftIcon, ChevronRightIcon, Spinner, Tooltip } from '@shared/ui';
import type { WizardOptions, WizardState } from '@shared/types/wizard';
import { StepLandingPages } from '../../Components/wizard/StepLandingPages';
import { StepTargeting } from '../../Components/wizard/StepTargeting';
import { StepWebsite } from '../../Components/wizard/StepWebsite';
import { WizardProgress } from '../../Components/wizard/WizardProgress';
import { blankState, fromPayload, toPayload, useProjectWizard } from '../../Components/wizard/useProjectWizard';
import { missingFor } from '../../Components/wizard/validation';

interface Props extends WizardOptions {
    draft: { step: number; payload: Record<string, unknown> } | null;
}

const TITLES = [
    ["The website you're promoting", 'The site, what to call the project, and what it is about.'],
    ['Targeting', 'Who should see it, and what a writer needs to know. All optional.'],
    ['Landing pages', 'The pages links point to, and the words they use.'],
];

export default function ProjectsCreate({ draft, ...options }: Props) {
    const initial = useMemo<WizardState>(
        () =>
            draft === null
                ? blankState(options.colors[0] ?? '#1d4ed8')
                : fromPayload(draft.payload, options.colors[0] ?? '#1d4ed8'),
        // Resumed once, on mount. Re-deriving on every render would fight the
        // edits being made on top of it.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [],
    );

    const { state, patch, step, goTo, savedAt, saving } = useProjectWizard(initial, draft?.step ?? 1);
    const [furthest, setFurthest] = useState(draft?.step ?? 1);
    const [submitting, setSubmitting] = useState(false);

    // Server-side errors, keyed the way the request returns them.
    const form = useForm({});
    const errors = form.errors as Record<string, string>;

    const missing = missingFor(step, state);
    const ready = missing.length === 0;

    function next() {
        if (!ready) return;

        const target = Math.min(3, step + 1);
        setFurthest((f) => Math.max(f, target));
        goTo(target);
    }

    function submit() {
        if (!ready) return;

        setSubmitting(true);

        router.post('/projects', toPayload(state) as never, {
            onFinish: () => setSubmitting(false),
            onError: (bag) => {
                // Send the person to the step that owns the first failure,
                // rather than leaving them on a page with no visible error.
                const keys = Object.keys(bag);
                if (keys.some((key) => key.startsWith('landing_pages'))) goTo(3);
                else if (keys.some((key) => ['publisher_task', 'country_ids', 'language_ids'].includes(key))) goTo(2);
                else goTo(1);
            },
        });
    }

    const [title, blurb] = TITLES[step - 1] ?? TITLES[0]!;

    return (
        <AppShell title="Projects" crumbs={[{ label: 'My projects', href: '/projects' }, { label: 'New project' }]}>
            <Head title="Create project" />

            <div className="mx-auto max-w-2xl">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="font-sora text-xl font-semibold text-ink-900">Create project</h1>
                    <DraftStatus saving={saving} savedAt={savedAt} />
                </div>

                <div className="mt-4">
                    <WizardProgress step={step} furthest={furthest} onJump={goTo} />
                </div>

                <section className="mt-5 rounded-card border border-subtle bg-card p-5 shadow-card">
                    <h2 className="font-sora text-md font-semibold text-ink-900">{title}</h2>
                    <p className="mb-5 mt-1 text-sm text-ink-500">{blurb}</p>

                    {step === 1 && <StepWebsite state={state} options={options} errors={errors} onChange={patch} />}
                    {step === 2 && <StepTargeting state={state} options={options} errors={errors} onChange={patch} />}
                    {step === 3 && <StepLandingPages state={state} errors={errors} onChange={patch} />}
                </section>

                <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                    {step === 1 ? (
                        <Link href="/projects">
                            <Button variant="ghost" type="button">
                                Cancel
                            </Button>
                        </Link>
                    ) : (
                        <Button variant="secondary" type="button" onClick={() => goTo(step - 1)}>
                            <ChevronLeftIcon size={14} />
                            Back
                        </Button>
                    )}

                    <GatedButton missing={missing}>
                        {step < 3 ? (
                            <Button type="button" disabled={!ready} onClick={next}>
                                Next
                                <ChevronRightIcon size={14} />
                            </Button>
                        ) : (
                            <Button type="button" disabled={!ready} loading={submitting} onClick={submit}>
                                Create project
                            </Button>
                        )}
                    </GatedButton>
                </div>

                {draft !== null && (
                    <p className="mt-4 text-center text-sm text-ink-500">
                        Picked up where you left off.{' '}
                        <button
                            type="button"
                            onClick={() => router.delete('/projects/draft')}
                            className="font-medium text-brand underline underline-offset-2"
                        >
                            Start over
                        </button>
                    </p>
                )}
            </div>
        </AppShell>
    );
}

/**
 * A disabled button that says what it is waiting for.
 *
 * The tooltip wraps the button rather than living on it, because a disabled
 * button fires no pointer events and a tooltip attached to one never opens —
 * which is how "disabled with no explanation" usually happens by accident.
 */
function GatedButton({ missing, children }: { missing: string[]; children: React.ReactNode }) {
    if (missing.length === 0) return <>{children}</>;

    return (
        <Tooltip content={`Still needed: ${missing.join(' ')}`}>
            <span className="inline-block">{children}</span>
        </Tooltip>
    );
}

function DraftStatus({ saving, savedAt }: { saving: boolean; savedAt: string | null }) {
    if (saving) {
        return (
            <span className="flex items-center gap-1.5 text-sm text-ink-500">
                <Spinner size={12} />
                Saving…
            </span>
        );
    }

    if (savedAt === null) return null;

    return (
        <span className={cn('text-sm text-ink-500')}>
            Draft saved {new Date(savedAt).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}
        </span>
    );
}
