import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react';
import { cn } from '@shared/lib/cn';
import { Spinner } from './icons';

export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger';
export type ButtonSize = 'sm' | 'md' | 'lg';

export interface ButtonProps extends Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'children'> {
    variant?: ButtonVariant;
    size?: ButtonSize;
    /** Shows a spinner and blocks interaction. The label stays in place so the
     *  button does not change width mid-action. */
    loading?: boolean;
    /** Renders the button in its error state — paired with a message elsewhere,
     *  never as the only signal that something failed. */
    error?: boolean;
    leadingIcon?: ReactNode;
    trailingIcon?: ReactNode;
    children: ReactNode;
}

const VARIANTS: Record<ButtonVariant, string> = {
    primary: 'bg-brand text-white hover:bg-brand-hover active:bg-brand-pressed',
    secondary: 'border border-subtle bg-card text-ink-700 hover:bg-sunken active:bg-ink-300',
    ghost: 'text-ink-700 hover:bg-sunken active:bg-ink-300',
    danger: 'bg-danger text-white hover:bg-danger-pressed active:bg-danger-pressed',
};

const SIZES: Record<ButtonSize, string> = {
    sm: 'h-8 gap-1.5 px-3 text-sm',
    md: 'h-9 gap-2 px-4 text-base',
    lg: 'h-11 gap-2 px-5 text-md',
};

/**
 * Buttons name the outcome — "Add to cart", "Publish project", "Top up
 * balance". Never a bare "Submit", never an arrow character glued to the label:
 * pass `trailingIcon` if a glyph is genuinely needed.
 */
export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
    {
        variant = 'primary',
        size = 'md',
        loading = false,
        error = false,
        leadingIcon,
        trailingIcon,
        className,
        disabled,
        type = 'button',
        children,
        ...props
    },
    ref,
) {
    return (
        <button
            ref={ref}
            type={type}
            disabled={disabled ?? loading}
            aria-busy={loading || undefined}
            aria-invalid={error || undefined}
            className={cn(
                'inline-flex select-none items-center justify-center rounded-button font-sora font-medium',
                'transition-colors duration-fast ease-standard',
                'disabled:pointer-events-none disabled:opacity-50',
                VARIANTS[variant],
                SIZES[size],
                error && 'ring-2 ring-danger',
                className,
            )}
            {...props}
        >
            {loading ? <Spinner size={size === 'lg' ? 18 : 15} /> : leadingIcon}
            {children}
            {!loading && trailingIcon}
        </button>
    );
});
