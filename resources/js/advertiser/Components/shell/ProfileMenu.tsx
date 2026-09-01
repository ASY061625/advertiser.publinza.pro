import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Avatar, useDismiss } from '@shared/ui';
import type { User } from '@shared/types';

const LINKS = [
    { label: 'Account settings', href: '/settings/account' },
    { label: 'Company details', href: '/settings/company' },
    { label: 'Billing', href: '/billing' },
    { label: 'API keys', href: '/settings/api-keys' },
];

export function ProfileMenu({ user }: { user: User }) {
    const [open, setOpen] = useState(false);
    const ref = useDismiss<HTMLDivElement>(open, () => setOpen(false));

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                aria-haspopup="menu"
                aria-expanded={open}
                aria-label={`Account menu for ${user.name}`}
                className="flex items-center rounded-pill transition-opacity duration-fast hover:opacity-90"
            >
                <Avatar name={user.name} size="md" />
            </button>

            {open && (
                <div
                    role="menu"
                    className="absolute right-0 z-50 mt-1 w-60 animate-scale-in overflow-hidden rounded-card border border-subtle bg-card py-1 shadow-card"
                >
                    <div className="border-b border-subtle px-4 py-3">
                        <p className="truncate font-sora text-base font-medium text-ink-900">{user.name}</p>
                        <p className="truncate text-sm text-ink-500">{user.email}</p>
                    </div>

                    {LINKS.map((link) => (
                        <Link
                            key={link.href}
                            href={link.href}
                            role="menuitem"
                            onClick={() => setOpen(false)}
                            className="block px-4 py-2 text-base text-ink-700 transition-colors duration-fast hover:bg-sunken"
                        >
                            {link.label}
                        </Link>
                    ))}

                    <div className="my-1 border-t border-subtle" />

                    <button
                        type="button"
                        role="menuitem"
                        onClick={() => {
                            setOpen(false);
                            router.post('/logout');
                        }}
                        className="block w-full px-4 py-2 text-left text-base text-ink-700 transition-colors duration-fast hover:bg-sunken"
                    >
                        Log out
                    </button>
                </div>
            )}
        </div>
    );
}
