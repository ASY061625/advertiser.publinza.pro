import type { ReactNode } from 'react';
import { cn } from '@shared/lib/cn';

/** One component's section of the gallery. */
export function Section({
    id,
    title,
    note,
    children,
}: {
    id: string;
    title: string;
    note?: string;
    children: ReactNode;
}) {
    return (
        <section id={id} className="scroll-mt-20 border-t border-subtle pt-10">
            <h2 className="font-sora text-lg font-semibold text-ink-900">{title}</h2>
            {note && <p className="mt-1 max-w-2xl text-base text-ink-500">{note}</p>}
            <div className="mt-5 flex flex-col gap-6">{children}</div>
        </section>
    );
}

/** A labelled row of variants — one state per row, named on the left. */
export function Row({ label, children, stack = false }: { label: string; children: ReactNode; stack?: boolean }) {
    return (
        <div className="grid grid-cols-[132px_1fr] items-start gap-5">
            <span className="pt-1.5 text-sm text-ink-500">{label}</span>
            <div className={cn('flex gap-3', stack ? 'flex-col items-start' : 'flex-wrap items-center')}>
                {children}
            </div>
        </div>
    );
}

/** A panel for components that need room, e.g. a table or a toolbar. */
export function Panel({ children }: { children: ReactNode }) {
    return <div className="rounded-card border border-subtle bg-canvas p-5">{children}</div>;
}

export function Swatch({ name, token, className }: { name: string; token: string; className: string }) {
    return (
        <div className="flex flex-col gap-1.5">
            <div className={cn('h-14 w-full rounded-card border border-subtle', className)} />
            <span className="text-sm font-medium text-ink-700">{name}</span>
            <code className="text-xs text-ink-500">{token}</code>
        </div>
    );
}
