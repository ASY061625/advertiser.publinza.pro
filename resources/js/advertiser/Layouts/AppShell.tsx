import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { Drawer, ToastProvider } from '@shared/ui';
import type { AdvertiserSharedProps } from '@shared/types';
import type { Shell } from '@shared/types/shell';
import { CommandPalette } from '../Components/shell/CommandPalette';
import { Header, type Crumb } from '../Components/shell/Header';
import { Sidebar } from '../Components/shell/Sidebar';
import { WhatsNewDrawer } from '../Components/shell/WhatsNewDrawer';
import { useShellCounts } from '../Components/shell/useShellCounts';

const SIDEBAR_KEY = 'publinza.sidebar.collapsed';

interface AppShellProps {
    title: string;
    /** Falls back to a single crumb of the page title. */
    crumbs?: Crumb[];
    children: ReactNode;
}

/**
 * The persistent shell every authenticated route renders inside.
 *
 * The sidebar's collapsed state is read from localStorage first so it paints in
 * its remembered state on the very first frame, then reconciled with the value
 * the server sent — localStorage is the fast path, the users table is the
 * source of truth across browsers.
 */
export function AppShell({ title, crumbs, children }: AppShellProps) {
    const page = usePage<AdvertiserSharedProps & { shell: Shell }>();
    const shell = page.props.shell;
    const user = page.props.auth.user;

    const [collapsed, setCollapsed] = useState<boolean>(() => {
        try {
            const stored = window.localStorage.getItem(SIDEBAR_KEY);
            if (stored !== null) return stored === 'true';
        } catch {
            // Private mode or blocked storage; the server value stands.
        }

        return shell.sidebarCollapsed;
    });

    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const [whatsNewOpen, setWhatsNewOpen] = useState(false);
    const [paletteOpen, setPaletteOpen] = useState(false);
    const [changelogRead, setChangelogRead] = useState(false);

    const { counts } = useShellCounts(shell.counts, shell.echo, user?.id ?? null);

    const toggleSidebar = useCallback(() => {
        setCollapsed((current) => {
            const next = !current;

            try {
                window.localStorage.setItem(SIDEBAR_KEY, String(next));
            } catch {
                // Not fatal: the write below still persists it per account.
            }

            // Fire and forget. The UI has already moved; a failed write only
            // means the next new browser starts expanded.
            void fetch('/shell/sidebar', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ collapsed: next }),
            }).catch(() => undefined);

            return next;
        });
    }, []);

    // Cmd/Ctrl+K anywhere except inside a field, where it may mean something
    // to the field itself.
    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if (event.key.toLowerCase() !== 'k' || !(event.metaKey || event.ctrlKey)) return;

            event.preventDefault();
            setPaletteOpen((current) => !current);
        }

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, []);

    // Close the off-canvas nav on navigation, otherwise it stays over the page
    // it just moved to.
    useEffect(() => setMobileNavOpen(false), [page.url]);

    const activeProjectId = (() => {
        const value = new URLSearchParams(page.url.split('?')[1] ?? '').get('project');

        return value === null ? null : Number(value);
    })();

    const changelogCount = changelogRead ? 0 : counts.changelog;

    return (
        <ToastProvider>
            <div className="min-h-screen bg-canvas">
                <a
                    href="#main-content"
                    className="sr-only-focusable absolute left-4 top-4 z-50 rounded-button bg-brand px-4 py-2 font-sora text-base font-medium text-white"
                >
                    Skip to content
                </a>

                <div className="fixed inset-y-0 left-0 z-40 hidden lg:block">
                    <Sidebar
                        collapsed={collapsed}
                        onToggle={toggleSidebar}
                        currentUrl={page.url}
                        projects={shell.projects}
                        activeProjectId={activeProjectId}
                        version={shell.version}
                    />
                </div>

                <div className={collapsed ? 'lg:pl-sidebar-collapsed' : 'lg:pl-sidebar'}>
                    <Header
                        crumbs={crumbs ?? [{ label: title }]}
                        shell={shell}
                        counts={{ ...counts, changelog: changelogCount }}
                        user={user!}
                        onOpenWhatsNew={() => setWhatsNewOpen(true)}
                        onOpenMobileNav={() => setMobileNavOpen(true)}
                    />

                    <main id="main-content" className="mx-auto max-w-content px-4 py-6 lg:px-6">
                        {children}
                    </main>
                </div>

                {/* Below lg the sidebar is an off-canvas drawer. Drawer already traps
                focus, locks body scroll and answers Escape. */}
                <Drawer
                    open={mobileNavOpen}
                    onClose={() => setMobileNavOpen(false)}
                    title="Menu"
                    className="max-w-sidebar"
                >
                    <Sidebar
                        mobile
                        collapsed={false}
                        onToggle={() => undefined}
                        currentUrl={page.url}
                        projects={shell.projects}
                        activeProjectId={activeProjectId}
                        version={shell.version}
                    />
                </Drawer>

                <WhatsNewDrawer
                    open={whatsNewOpen}
                    onClose={() => setWhatsNewOpen(false)}
                    onRead={() => setChangelogRead(true)}
                />

                <CommandPalette open={paletteOpen} onClose={() => setPaletteOpen(false)} />
            </div>
        </ToastProvider>
    );
}
