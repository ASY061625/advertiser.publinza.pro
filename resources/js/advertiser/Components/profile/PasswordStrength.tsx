import { useMemo } from 'react';
import { cn } from '@shared/lib/cn';

/**
 * How strong a password is, as four bars and a sentence.
 *
 * Deliberately not a score out of 100: a number invites people to optimise it,
 * and what actually matters is whether the thing has enough different kinds of
 * character and enough of them. The bar is the nudge; the server's own
 * Password::defaults() is the rule, and this never contradicts it.
 *
 * The advice under the bar names the specific missing piece rather than saying
 * "weak", because "weak" is a verdict and "add a number" is an instruction.
 */
export function PasswordStrength({ value }: { value: string }) {
    const result = useMemo(() => assess(value), [value]);

    if (value === '') return null;

    return (
        <div className="mt-2">
            <div className="flex gap-1" role="img" aria-label={`Password strength: ${result.label}`}>
                {[0, 1, 2, 3].map((index) => (
                    <span
                        key={index}
                        className={cn(
                            'h-1 flex-1 rounded-pill transition-colors duration-fast',
                            index < result.score ? result.colour : 'bg-ink-300/40',
                        )}
                    />
                ))}
            </div>

            <p className={cn('mt-1.5 text-sm', result.score >= 3 ? 'text-ink-500' : 'text-ink-700')}>
                <span className="font-medium">{result.label}.</span> {result.advice}
            </p>
        </div>
    );
}

interface Assessment {
    score: number;
    label: string;
    advice: string;
    colour: string;
}

function assess(value: string): Assessment {
    const missing: string[] = [];

    if (value.length < 10) missing.push('make it at least 10 characters');
    if (!/[a-z]/.test(value)) missing.push('add a lowercase letter');
    if (!/[A-Z]/.test(value)) missing.push('add a capital');
    if (!/\d/.test(value)) missing.push('add a number');

    // The server requires length, mixed case and a number. Symbols and real
    // length are what take it past "allowed" into "good".
    const bonus = (/[^A-Za-z0-9]/.test(value) ? 1 : 0) + (value.length >= 16 ? 1 : 0);

    if (missing.length > 0) {
        return {
            score: Math.max(1, 3 - missing.length),
            label: missing.length > 2 ? 'Too weak' : 'Not quite',
            advice: sentence(missing),
            colour: missing.length > 2 ? 'bg-danger' : 'bg-warning',
        };
    }

    if (bonus === 0) {
        return {
            score: 3,
            label: 'Good',
            advice: 'A symbol or a few more characters would make it stronger.',
            colour: 'bg-warning',
        };
    }

    return {
        score: 4,
        label: 'Strong',
        advice: 'That will do nicely.',
        colour: 'bg-teal',
    };
}

/** "add a capital and add a number" reads badly; join them properly. */
function sentence(parts: string[]): string {
    const capitalised = parts.map((part, index) => (index === 0 ? part.charAt(0).toUpperCase() + part.slice(1) : part));

    if (capitalised.length === 1) return `${capitalised[0]}.`;

    return `${capitalised.slice(0, -1).join(', ')} and ${capitalised[capitalised.length - 1]}.`;
}
