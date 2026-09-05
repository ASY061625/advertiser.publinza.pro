import { CheckIcon } from '@shared/ui';
import { cn } from '@shared/lib/cn';

interface Props {
    step: number;
    /** How far the advertiser has legitimately reached; ahead of it is not clickable. */
    furthest: number;
    onJump: (step: number) => void;
}

const STEPS = ['Project', 'Website', 'Content', 'Review'];

/**
 * Where you are in four steps.
 *
 * Completed steps are buttons because going back is the point — and going back
 * loses nothing, since every step renders from one state object. Steps ahead
 * are not, because arriving at Content with no site chosen would render a form
 * that cannot say whose minimum it is checking against.
 */
export function WizardSteps({ step, furthest, onJump }: Props) {
    return (
        <ol className="flex items-center gap-2" aria-label="Progress">
            {STEPS.map((label, index) => {
                const number = index + 1;
                const done = number < step;
                const current = number === step;
                const reachable = number <= furthest;

                return (
                    <li key={label} className="flex flex-1 items-center gap-2">
                        <button
                            type="button"
                            disabled={!reachable || current}
                            aria-current={current ? 'step' : undefined}
                            onClick={() => onJump(number)}
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
                                {done ? <CheckIcon size={14} /> : number}
                            </span>

                            <span
                                className={cn(
                                    'hidden text-sm font-medium sm:block',
                                    current ? 'text-ink-900' : 'text-ink-500',
                                )}
                            >
                                {label}
                            </span>
                        </button>

                        {index < STEPS.length - 1 && (
                            <span
                                aria-hidden="true"
                                className={cn('h-px flex-1', done ? 'bg-brand' : 'bg-ink-300')}
                            />
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
