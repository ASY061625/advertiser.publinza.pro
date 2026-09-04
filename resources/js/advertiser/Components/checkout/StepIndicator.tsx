import { router } from '@inertiajs/react';
import { CheckIcon } from '@shared/ui';
import { cn } from '@shared/lib/cn';

interface Props {
    steps: string[];
    current: string;
    /** How far forward the buyer is allowed to jump. */
    furthest: number;
}

const LABELS: Record<string, { title: string; blurb: string }> = {
    review: { title: 'Review', blurb: 'What you are buying' },
    content: { title: 'Content', blurb: 'Your articles' },
    confirm: { title: 'Confirm and pay', blurb: 'Billing and payment' },
};

/**
 * Where you are, and what is behind you.
 *
 * A completed step is a link back, because reviewing is the whole point of the
 * first step and a buyer who spots a wrong anchor on the confirm screen needs
 * to be one click from fixing it. Steps ahead are not links: they are not
 * finished, and offering them would be offering to skip work the last step
 * depends on.
 */
export function StepIndicator({ steps, current, furthest }: Props) {
    const index = steps.indexOf(current);

    return (
        <ol className="flex flex-wrap items-center gap-x-2 gap-y-3">
            {steps.map((step, position) => {
                const done = position < index;
                const active = position === index;
                const reachable = position <= furthest;

                const inner = (
                    <>
                        <span
                            aria-hidden="true"
                            className={cn(
                                'num flex size-7 shrink-0 items-center justify-center rounded-full border text-sm font-semibold',
                                done && 'border-brand bg-brand text-white',
                                active && 'border-brand text-brand',
                                !done && !active && 'border-subtle text-ink-500',
                            )}
                        >
                            {done ? <CheckIcon size={14} /> : position + 1}
                        </span>

                        <span className="min-w-0">
                            <span
                                className={cn(
                                    'block font-sora text-base font-semibold',
                                    active ? 'text-ink-900' : 'text-ink-500',
                                )}
                            >
                                {LABELS[step]?.title ?? step}
                            </span>
                            <span className="hidden text-xs text-ink-500 sm:block">{LABELS[step]?.blurb}</span>
                        </span>
                    </>
                );

                return (
                    <li key={step} className="flex items-center gap-2">
                        {reachable && !active ? (
                            <button
                                type="button"
                                onClick={() => router.visit(`/checkout?step=${step}`)}
                                className="flex items-center gap-2 rounded-button px-1 py-0.5 hover:bg-sunken"
                            >
                                {inner}
                            </button>
                        ) : (
                            <span
                                className="flex items-center gap-2 px-1 py-0.5"
                                aria-current={active ? 'step' : undefined}
                            >
                                {inner}
                            </span>
                        )}

                        {position < steps.length - 1 && (
                            <span aria-hidden="true" className="hidden h-px w-8 bg-subtle sm:block" />
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
