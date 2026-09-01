import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@shared/lib/cn';
import { DangerIcon, InfoIcon, SuccessIcon, WarningIcon, XIcon } from './icons';

export type ToastTone = 'info' | 'success' | 'warning' | 'danger';

export interface ToastMessage {
    id: string;
    tone: ToastTone;
    /** Reports what happened, in the past tense: "Published", "Balance topped up". */
    title: string;
    description?: string;
    duration?: number;
}

const TONES: Record<ToastTone, { icon: ReactNode; accent: string }> = {
    info: { icon: <InfoIcon size={18} />, accent: 'text-brand' },
    success: { icon: <SuccessIcon size={18} />, accent: 'text-success' },
    warning: { icon: <WarningIcon size={18} />, accent: 'text-warning' },
    danger: { icon: <DangerIcon size={18} />, accent: 'text-danger' },
};

interface ToastContextValue {
    toast: (message: Omit<ToastMessage, 'id'>) => void;
}

const ToastContext = createContext<ToastContextValue | null>(null);

export function useToast(): ToastContextValue {
    const context = useContext(ToastContext);
    if (!context) throw new Error('useToast must be used inside a <ToastProvider>.');

    return context;
}

export function ToastProvider({ children }: { children: ReactNode }) {
    const [messages, setMessages] = useState<ToastMessage[]>([]);

    const dismiss = useCallback((id: string) => {
        setMessages((current) => current.filter((message) => message.id !== id));
    }, []);

    const toast = useCallback((message: Omit<ToastMessage, 'id'>) => {
        setMessages((current) => [...current, { ...message, id: crypto.randomUUID() }]);
    }, []);

    const value = useMemo(() => ({ toast }), [toast]);

    return (
        <ToastContext.Provider value={value}>
            {children}
            <ToastViewport messages={messages} onDismiss={dismiss} />
        </ToastContext.Provider>
    );
}

function ToastViewport({ messages, onDismiss }: { messages: ToastMessage[]; onDismiss: (id: string) => void }) {
    if (typeof document === 'undefined') return null;

    return createPortal(
        // `polite` so a toast never interrupts what a screen reader is saying.
        <div
            aria-live="polite"
            aria-atomic="false"
            className="pointer-events-none fixed bottom-4 right-4 z-50 flex w-full max-w-sm flex-col gap-2"
        >
            {messages.map((message) => (
                <Toast key={message.id} message={message} onDismiss={onDismiss} />
            ))}
        </div>,
        document.body,
    );
}

export function Toast({ message, onDismiss }: { message: ToastMessage; onDismiss: (id: string) => void }) {
    const { icon, accent } = TONES[message.tone];
    const { id, duration = 5000 } = message;

    useEffect(() => {
        const timer = window.setTimeout(() => onDismiss(id), duration);

        return () => window.clearTimeout(timer);
    }, [id, duration, onDismiss]);

    return (
        <div
            className={cn(
                'pointer-events-auto flex animate-toast-in items-start gap-3 rounded-card',
                'border border-subtle bg-card p-4 shadow-card',
            )}
        >
            <span className={cn('mt-0.5 shrink-0', accent)}>{icon}</span>

            <div className="min-w-0 flex-1">
                <p className="font-sora text-base font-medium text-ink-900">{message.title}</p>
                {message.description && <p className="mt-0.5 text-base text-ink-500">{message.description}</p>}
            </div>

            <button
                type="button"
                aria-label="Dismiss"
                onClick={() => onDismiss(id)}
                className="-m-1 shrink-0 rounded-button p-1 text-ink-500 transition-colors duration-fast hover:text-ink-700"
            >
                <XIcon size={16} />
            </button>
        </div>
    );
}
