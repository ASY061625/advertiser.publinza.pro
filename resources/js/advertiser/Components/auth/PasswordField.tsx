import { useState } from 'react';
import { CheckIcon, Input } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { PASSWORD_STRENGTH_LABELS, passwordChecks, passwordScore } from './validation';

interface PasswordFieldProps {
    label: string;
    value: string;
    onChange: (value: string) => void;
    onBlur?: () => void;
    error?: string;
    /** Hides the meter and the checklist — used for the confirm field. */
    plain?: boolean;
    autoComplete?: string;
    id?: string;
}

const BAR_TONES = ['bg-ink-300', 'bg-danger', 'bg-gold', 'bg-brand', 'bg-teal'];

export function PasswordField({
    label,
    value,
    onChange,
    onBlur,
    error,
    plain = false,
    autoComplete = 'new-password',
    id = 'password',
}: PasswordFieldProps) {
    const [visible, setVisible] = useState(false);
    const score = passwordScore(value);
    const checks = passwordChecks(value);

    return (
        <div>
            <Input
                id={id}
                label={label}
                type={visible ? 'text' : 'password'}
                autoComplete={autoComplete}
                value={value}
                error={error}
                onChange={(event) => onChange(event.target.value)}
                onBlur={onBlur}
                trailingSlot={
                    <button
                        type="button"
                        onClick={() => setVisible((current) => !current)}
                        aria-label={visible ? 'Hide password' : 'Show password'}
                        className="rounded-button px-1 text-sm text-ink-500 transition-colors duration-fast hover:text-ink-700"
                    >
                        {visible ? 'Hide' : 'Show'}
                    </button>
                }
            />

            {!plain && (
                <div className="mt-3">
                    <div className="flex items-center gap-3">
                        <div className="flex flex-1 gap-1" aria-hidden="true">
                            {[1, 2, 3, 4].map((step) => (
                                <span
                                    key={step}
                                    className={cn(
                                        'h-1 flex-1 rounded-pill transition-colors duration-fast',
                                        step <= score ? BAR_TONES[score] : 'bg-sunken',
                                    )}
                                />
                            ))}
                        </div>
                        {/* Announced politely so a screen reader hears the change
                            without being interrupted on every keystroke. */}
                        <span aria-live="polite" className="w-12 text-right text-sm text-ink-500">
                            {PASSWORD_STRENGTH_LABELS[score]}
                        </span>
                    </div>

                    <ul className="mt-3 flex flex-col gap-1.5">
                        {checks.map((check) => (
                            <li
                                key={check.id}
                                className={cn(
                                    'flex items-center gap-2 text-sm',
                                    check.passed ? 'text-success' : 'text-ink-500',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-4 shrink-0 items-center justify-center rounded-pill',
                                        check.passed ? 'bg-success-bg' : 'bg-sunken',
                                    )}
                                >
                                    {check.passed && <CheckIcon size={11} />}
                                </span>
                                {check.label}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
