import type { ButtonHTMLAttributes } from 'react';
import { cn } from '@shared/lib/cn';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
}

const VARIANTS: Record<Variant, string> = {
    primary: 'bg-brand text-white hover:bg-brand-700',
    secondary: 'border border-ink-300 bg-surface-card text-ink-700 hover:bg-surface-sunken',
    ghost: 'text-ink-700 hover:bg-surface-sunken',
    danger: 'bg-danger text-white hover:brightness-95',
};

/** Buttons name the outcome: "Add to cart", not "Submit". */
export function Button({ variant = 'primary', className, type = 'button', ...props }: ButtonProps) {
    return (
        <button
            type={type}
            className={cn(
                'inline-flex h-9 items-center justify-center rounded-button px-4 font-sora text-base font-medium',
                'transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                VARIANTS[variant],
                className,
            )}
            {...props}
        />
    );
}
