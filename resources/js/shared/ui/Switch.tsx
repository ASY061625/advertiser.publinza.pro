import { useId } from 'react';
import { cn } from '@shared/lib/cn';

export interface SwitchProps {
    label: string;
    hint?: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    disabled?: boolean;
    /** Blocks interaction and dims the control while a request is in flight. */
    loading?: boolean;
    className?: string;
}

/**
 * A switch takes effect immediately — no Save button. If the change needs
 * confirming, use a Checkbox in a form instead.
 */
export function Switch({ label, hint, checked, onCheckedChange, disabled, loading = false, className }: SwitchProps) {
    const id = useId();
    const locked = disabled ?? loading;

    return (
        <div className={cn('flex items-start justify-between gap-4', className)}>
            <span className="flex flex-col gap-0.5">
                <label
                    htmlFor={id}
                    className={cn('text-base', locked ? 'text-ink-500' : 'cursor-pointer text-ink-700')}
                >
                    {label}
                </label>
                {hint && (
                    <span id={`${id}-hint`} className="text-sm text-ink-500">
                        {hint}
                    </span>
                )}
            </span>

            <button
                id={id}
                type="button"
                role="switch"
                aria-checked={checked}
                aria-describedby={hint ? `${id}-hint` : undefined}
                aria-busy={loading || undefined}
                disabled={locked}
                onClick={() => onCheckedChange(!checked)}
                className={cn(
                    'relative inline-flex h-5 w-9 shrink-0 items-center rounded-pill',
                    'transition-colors duration-fast ease-standard',
                    'disabled:cursor-not-allowed disabled:opacity-50',
                    checked ? 'bg-brand' : 'bg-ink-300',
                )}
            >
                <span
                    className={cn(
                        'inline-block size-4 rounded-pill bg-card shadow-card',
                        'transition-transform duration-fast ease-standard',
                        checked ? 'translate-x-[18px]' : 'translate-x-0.5',
                    )}
                />
            </button>
        </div>
    );
}
