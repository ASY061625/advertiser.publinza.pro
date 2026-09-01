import type { ReactNode } from 'react';
import { cn } from '@shared/lib/cn';
import { DangerIcon, InfoIcon, SuccessIcon, WarningIcon, XIcon } from './icons';

export type AlertTone = 'info' | 'success' | 'warning' | 'danger';

export interface AlertProps {
    tone?: AlertTone;
    title: string;
    /** Errors say what happened and what to do next. */
    children?: ReactNode;
    action?: ReactNode;
    onDismiss?: () => void;
    className?: string;
}

const TONES: Record<AlertTone, { wrap: string; icon: ReactNode }> = {
    info: { wrap: 'bg-info-bg text-brand', icon: <InfoIcon size={18} /> },
    success: { wrap: 'bg-success-bg text-success', icon: <SuccessIcon size={18} /> },
    warning: { wrap: 'bg-warning-bg text-warning', icon: <WarningIcon size={18} /> },
    danger: { wrap: 'bg-danger-bg text-danger', icon: <DangerIcon size={18} /> },
};

export function Alert({ tone = 'info', title, children, action, onDismiss, className }: AlertProps) {
    const { wrap, icon } = TONES[tone];

    return (
        <div
            role={tone === 'danger' ? 'alert' : 'status'}
            className={cn('flex gap-3 rounded-card p-4', wrap, className)}
        >
            <span className="mt-0.5 shrink-0">{icon}</span>

            <div className="min-w-0 flex-1">
                <p className="font-sora text-base font-medium">{title}</p>
                {children && <div className="mt-1 text-base text-ink-700">{children}</div>}
                {action && <div className="mt-3">{action}</div>}
            </div>

            {onDismiss && (
                <button
                    type="button"
                    onClick={onDismiss}
                    aria-label="Dismiss"
                    className="-m-1 h-fit shrink-0 rounded-button p-1 text-ink-500 transition-colors duration-fast hover:text-ink-700"
                >
                    <XIcon size={16} />
                </button>
            )}
        </div>
    );
}
