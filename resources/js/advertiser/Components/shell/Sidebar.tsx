import { Link } from '@inertiajs/react';
import { cn } from '@shared/lib/cn';
import { FolderIcon, GlobeIcon, HomeIcon, ListIcon, PanelLeftIcon, PlusIcon, Tooltip } from '@shared/ui';
import type { ShellProject } from '@shared/types/shell';
import { useAddPost } from '../post-wizard/AddPostProvider';
import { ProjectSwitcher } from './ProjectSwitcher';

interface SidebarProps {
    collapsed: boolean;
    onToggle: () => void;
    currentUrl: string;
    projects: ShellProject[];
    activeProjectId: number | null;
    version: string;
    /** Off-canvas on small screens, where collapsing does not apply. */
    mobile?: boolean;
}

/** Catalog and Posts carry the project scope; Dashboard and Lists do not. */
const NAV = [
    { label: 'Dashboard', href: '/dashboard', icon: HomeIcon, scoped: false },
    { label: 'My projects', href: '/projects', icon: FolderIcon, scoped: false },
    { label: 'Catalog of websites', href: '/catalog', icon: GlobeIcon, scoped: true },
    { label: 'My lists', href: '/lists', icon: ListIcon, scoped: false },
];

export function Sidebar({
    collapsed,
    onToggle,
    currentUrl,
    projects,
    activeProjectId,
    version,
    mobile = false,
}: SidebarProps) {
    const isCollapsed = collapsed && !mobile;
    const addPost = useAddPost();

    function hrefFor(item: (typeof NAV)[number]): string {
        return item.scoped && activeProjectId !== null ? `${item.href}?project=${activeProjectId}` : item.href;
    }

    return (
        <div
            className={cn(
                'flex h-full flex-col border-r border-subtle bg-card',
                mobile ? 'w-sidebar' : isCollapsed ? 'w-sidebar-collapsed' : 'w-sidebar',
            )}
        >
            {/* Wordmark shrinks to the mark alone when collapsed. */}
            <div className={cn('flex h-header shrink-0 items-center', isCollapsed ? 'justify-center px-0' : 'px-5')}>
                <Link href="/dashboard" className="font-sora text-md font-semibold text-ink-900">
                    {isCollapsed ? 'P' : 'Publinza'}
                </Link>
            </div>

            {/* The quick action, above the nav rather than inside it: it is a
                thing to do, not a place to go, and a button that looks like a
                nav item gets read as one. */}
            <div className={cn('px-3 pb-1', isCollapsed && 'px-2')}>
                <button
                    type="button"
                    onClick={() => addPost.open({ projectId: activeProjectId })}
                    title={isCollapsed ? 'Add post' : undefined}
                    className={cn(
                        'flex h-9 w-full items-center justify-center gap-2 rounded-button bg-brand font-sora text-sm font-medium text-white',
                        'transition-colors duration-fast ease-standard hover:bg-brand-hover active:bg-brand-pressed',
                    )}
                >
                    <PlusIcon size={16} />
                    {!isCollapsed && 'Add post'}
                    {isCollapsed && <span className="sr-only">Add post</span>}
                </button>
            </div>

            <nav aria-label="Main" className="flex flex-col gap-1 px-3 py-2">
                {NAV.map((item) => {
                    const Icon = item.icon;
                    const active = currentUrl.split('?')[0] === item.href;

                    const link = (
                        <Link
                            href={hrefFor(item)}
                            aria-current={active ? 'page' : undefined}
                            className={cn(
                                'relative flex items-center gap-3 rounded-button py-2 font-sora text-base font-medium',
                                'transition-colors duration-fast ease-standard',
                                isCollapsed ? 'justify-center px-0' : 'px-3',
                                active ? 'bg-brand-subtle text-brand' : 'text-ink-700 hover:bg-sunken',
                            )}
                        >
                            {/* 3px bar on the left edge of the active pill. */}
                            {active && (
                                <span
                                    aria-hidden="true"
                                    className="absolute inset-y-1 left-0 w-[3px] rounded-pill bg-brand"
                                />
                            )}
                            <Icon size={18} className="shrink-0" />
                            {!isCollapsed && <span className="truncate">{item.label}</span>}
                        </Link>
                    );

                    // Collapsed labels become tooltips, reachable by hover and
                    // by keyboard focus.
                    return isCollapsed ? (
                        <Tooltip key={item.href} content={item.label} side="bottom">
                            {link}
                        </Tooltip>
                    ) : (
                        <div key={item.href}>{link}</div>
                    );
                })}
            </nav>

            <div className="py-2">
                <ProjectSwitcher projects={projects} activeId={activeProjectId} collapsed={isCollapsed} />
            </div>

            <div className="flex-1" />

            <div className={cn('flex flex-col gap-1 pb-2', isCollapsed ? 'px-2' : 'px-3')}>
                {isCollapsed ? (
                    <Tooltip content="Support" side="top">
                        <a
                            href="mailto:hello@publinza.pro"
                            className="flex justify-center rounded-button px-0 py-2 text-sm text-ink-500 hover:bg-sunken hover:text-ink-700"
                        >
                            ?
                        </a>
                    </Tooltip>
                ) : (
                    <a
                        href="mailto:hello@publinza.pro"
                        className="rounded-button px-3 py-2 text-sm text-ink-500 transition-colors duration-fast hover:bg-sunken hover:text-ink-700"
                    >
                        Support
                    </a>
                )}

                {!isCollapsed && <p className="num px-3 text-xs text-ink-500">Version {version}</p>}
            </div>

            {/* The collapse toggle sits on the sidebar's bottom edge. */}
            {!mobile && (
                <button
                    type="button"
                    onClick={onToggle}
                    aria-expanded={!collapsed}
                    aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                    className={cn(
                        'flex h-11 shrink-0 items-center gap-2.5 border-t border-subtle text-sm text-ink-500',
                        'transition-colors duration-fast hover:bg-sunken hover:text-ink-700',
                        isCollapsed ? 'justify-center px-0' : 'px-5',
                    )}
                >
                    <PanelLeftIcon size={16} className="shrink-0" />
                    {!isCollapsed && <span>Collapse</span>}
                </button>
            )}
        </div>
    );
}
