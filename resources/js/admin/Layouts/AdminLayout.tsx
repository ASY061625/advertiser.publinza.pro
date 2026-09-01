import type { ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { cn } from '@shared/lib/cn';
import type { AdminSharedProps } from '@shared/types';

const NAV = [
    { label: 'Overview', href: '/asylogin' },
    { label: 'Sites', href: '/asylogin/sites' },
    { label: 'Orders', href: '/asylogin/orders' },
    { label: 'Payouts', href: '/asylogin/payouts' },
    { label: 'Users', href: '/asylogin/users' },
];

export function AdminLayout({ title, children }: { title: string; children: ReactNode }) {
    const { props, url } = usePage<AdminSharedProps>();

    return (
        <div className="min-h-screen bg-canvas">
            <aside className="fixed inset-y-0 left-0 z-40 flex w-sidebar flex-col border-r border-ink-300 bg-ink-900">
                <div className="flex h-header items-center px-5 font-sora text-md font-semibold text-white">
                    Publinza admin
                </div>
                <nav className="flex flex-1 flex-col gap-1 px-3 py-4">
                    {NAV.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={cn(
                                'rounded-button px-3 py-2 font-sora text-base font-medium',
                                url === item.href ? 'bg-white/10 text-white' : 'text-ink-300 hover:bg-white/5',
                            )}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>
            </aside>

            <div className="pl-sidebar">
                <header className="sticky top-0 z-30 flex h-header items-center justify-between border-b border-ink-300 bg-card px-6">
                    <h1 className="font-sora text-lg font-semibold text-ink-900">{title}</h1>
                    <span className="text-sm text-ink-500">
                        {props.auth.admin?.name} · {props.auth.admin?.role}
                    </span>
                </header>

                <main className="mx-auto max-w-content px-6 py-6">{children}</main>
            </div>
        </div>
    );
}
