/**
 * Client-side rules for inline, on-blur validation.
 *
 * These mirror the server's rules and, deliberately, its exact wording — a
 * field that reads one thing on blur and another after submit reads as two
 * different systems disagreeing. The server remains the authority; this only
 * shortens the feedback loop.
 *
 * Every message says what is wrong and what to do. None of them says "invalid".
 */

export type Validator = (value: string, form?: Record<string, string>) => string | null;

export const required =
    (what: string): Validator =>
    (value) =>
        value.trim() === '' ? `Enter ${what}.` : null;

export const email: Validator = (value) => {
    if (value.trim() === '') return 'Enter your work email address.';

    // Deliberately loose: the server checks deliverability. This only catches
    // the obvious typo before someone submits.
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value.trim())) {
        return 'That address is missing something — check for a typo in the domain.';
    }

    return null;
};

export const minLength =
    (min: number, message: string): Validator =>
    (value) =>
        value.length < min ? message : null;

export const matches =
    (field: string, message: string): Validator =>
    (value, form) =>
        form && value !== form[field] ? message : null;

export interface PasswordCheck {
    id: string;
    label: string;
    passed: boolean;
}

/** The requirements list shown under the password field, always visible. */
export function passwordChecks(value: string): PasswordCheck[] {
    return [
        { id: 'length', label: 'At least 10 characters', passed: value.length >= 10 },
        { id: 'case', label: 'An upper and a lower-case letter', passed: /[a-z]/.test(value) && /[A-Z]/.test(value) },
        { id: 'number', label: 'At least one number', passed: /\d/.test(value) },
    ];
}

/**
 * A 0–4 score for the meter.
 *
 * Length is weighted above character classes because it does more for real
 * strength, and the meter should not tell someone that "P@ss1!" is strong.
 */
export function passwordScore(value: string): number {
    if (value.length === 0) return 0;

    let score = 0;
    if (value.length >= 10) score++;
    if (value.length >= 14) score++;
    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
    if (/\d/.test(value) && /[^\w\s]/.test(value)) score++;

    // A password that fails the hard requirements never reads above "weak",
    // whatever else it has going for it.
    const meetsMinimum = passwordChecks(value).every((check) => check.passed);

    return meetsMinimum ? Math.min(4, Math.max(1, score)) : Math.min(1, score);
}

export const PASSWORD_STRENGTH_LABELS = ['', 'Weak', 'Fair', 'Good', 'Strong'] as const;
