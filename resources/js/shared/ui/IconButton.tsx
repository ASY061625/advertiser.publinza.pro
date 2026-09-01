import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react';
import { cn } from '@shared/lib/cn';
import { Spinner } from './icons';
import type { ButtonSize, ButtonVariant } from './Button';

export interface IconButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: ButtonVariant;
    size?: ButtonSize;
    loading?: boolean;
    /** Required: an icon-only control has no visible text to name it. */
    label: string;
    icon: ReactNode;
}

const VARIANTS: Record<ButtonVariant, string> = {
    primary: 'bg-brand text-white hover:bg-brand-hover active:bg-brand-pressed',
    secondary: 'border border-subtle bg-card text-ink-700 hover:bg-sunken active:bg-ink-300',
    ghost: 'text-ink-500 hover:bg-sunken hover:text-ink-700 active:bg-ink-300',
    danger: 'bg-danger text-white hover:bg-danger-pressed active:bg-danger-pressed',
};

const SIZES: Record<ButtonSize, string> = { sm: 'size-8', md: 'size-9', lg: 'size-11' };

export const IconButton = forwardRef<HTMLButtonElement, IconButtonProps>(function IconButton(
    { variant = 'ghost', size = 'md', loading = false, label, icon, className, disabled, type = 'button', ...props },
    ref,
) {
    return (
        <button
            ref={ref}
            type={type}
            aria-label={label}
            title={label}
            disabled={disabled ?? loading}
            aria-busy={loading || undefined}
            className={cn(
                'inline-flex shrink-0 items-center justify-center rounded-button',
                'transition-colors duration-fast ease-standard',
                'disabled:pointer-events-none disabled:opacity-50',
                VARIANTS[variant],
                SIZES[size],
                className,
            )}
            {...props}
        >
            {loading ? <Spinner size={16} /> : icon}
        </button>
    );
});
