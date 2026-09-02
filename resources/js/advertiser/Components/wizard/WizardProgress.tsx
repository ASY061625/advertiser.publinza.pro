import { cn } from '@shared/lib/cn';
import { CheckIcon } from '@shared/ui';

interface Props {
    step: number;
    /** How far the person has legitimately reached, so they cannot skip ahead. */
    furthest: number;
    onJump: (step: number) => void;
}

const STEPS = [
    { number: 1, label: 'Website' },
    { number: 2, label: 'Targeting' },
    { number: 3, label: 'Landing pages' },
];

/**
 * Where you are, and what is behind you.
 *
 * Completed steps are buttons — going back is the point of a wizard with a
 * back button — but steps ahead are not, because arriving at step 3 without a
 * URL would render a form that cannot describe what it is asking about.
 */
export function WizardProgress({ step, furthest, onJump }: Props) {
    return (
        <ol className="flex items-center gap-2" aria-label="Progress">
            {STEPS.map((item, index) => {
                const done = item.number < step;
                const current = item.number === step;
                const reachable = item.number <= furthest;

                return (
                    <li key={item.number} className="flex flex-1 items-center gap-2">
                        <button
                            type="button"
                            disabled={!reachable || current}
                            aria-current={current ? 'step' : undefined}
                            onClick={() => onJump(item.number)}
                            className={cn(
                                'flex items-center gap-2 rounded-button px-1 py-1 text-left',
                                'transition-colors duration-fast ease-standard',
                                reachable && !current && 'hover:bg-sunken',
                                !reachable && 'cursor-default',
                            )}
                        >
                            <span
                                className={cn(
                                    'num flex size-7 shrink-0 items-center justify-center rounded-pill text-sm font-medium',
                                    done && 'bg-brand text-white',
                                    current && 'bg-brand-subtle text-brand ring-2 ring-brand',
                                    !done && !current && 'bg-sunken text-ink-500',
                                )}
                            >
                                {done ? <CheckIcon size={14} /> : item.number}
                            </span>

                            <span
                                className={cn(
                                    'hidden text-sm font-medium sm:block',
                                    current ? 'text-ink-900' : 'text-ink-500',
                                )}
                            >
                                {item.label}
                            </span>
                        </button>

                        {index < STEPS.length - 1 && (
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'h-px flex-1 transition-colors duration-fast',
                                    done ? 'bg-brand' : 'bg-ink-300',
                                )}
                            />
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
