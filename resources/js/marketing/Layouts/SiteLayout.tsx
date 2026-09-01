import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';

const NAV = [
    { label: 'How it works', href: '/how-it-works' },
    { label: 'Pricing', href: '/pricing' },
    { label: 'Publishers', href: '/publishers' },
];

export function SiteLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-screen flex-col bg-card">
            <header className="sticky top-0 z-30 h-header border-b border-ink-300 bg-card">
                <div className="mx-auto flex h-full max-w-content items-center justify-between px-6">
                    <Link href="/" className="font-sora text-md font-semibold text-ink-900">
                        Publinza
                    </Link>
                    <nav className="flex items-center gap-6">
                        {NAV.map((item) => (
                            <Link key={item.href} href={item.href} className="text-base text-ink-700 hover:text-brand">
                                {item.label}
                            </Link>
                        ))}
                        <a
                            href="https://app.publinza.pro/login"
                            className="rounded-button bg-brand px-4 py-2 font-sora text-base font-medium text-white hover:bg-brand-hover"
                        >
                            Sign in
                        </a>
                    </nav>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="border-t border-ink-300 bg-sunken">
                <div className="mx-auto max-w-content px-6 py-10 text-base text-ink-500">
                    © {new Date().getFullYear()} Publinza
                </div>
            </footer>
        </div>
    );
}
