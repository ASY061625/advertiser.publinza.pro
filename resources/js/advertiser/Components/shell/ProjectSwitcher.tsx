import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { cn } from '@shared/lib/cn';
import { CheckIcon, ChevronDownIcon, SearchIcon, useDismiss } from '@shared/ui';
import type { ShellProject } from '@shared/types/shell';

interface ProjectSwitcherProps {
    projects: ShellProject[];
    activeId: number | null;
    collapsed: boolean;
}

/**
 * Scopes the buying context to one project.
 *
 * Choosing one appends `?project={id}` to the Catalog and Posts links, so the
 * context follows the advertiser rather than being re-picked on each screen.
 * "All projects" clears it.
 */
export function ProjectSwitcher({ projects, activeId, collapsed }: ProjectSwitcherProps) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const ref = useDismiss<HTMLDivElement>(open, () => setOpen(false));

    const active = projects.find((project) => project.id === activeId) ?? null;

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return needle === '' ? projects : projects.filter((p) => p.name.toLowerCase().includes(needle));
    }, [projects, query]);

    function choose(id: number | null) {
        setOpen(false);
        setQuery('');

        const url = new URL(window.location.href);
        if (id === null) url.searchParams.delete('project');
        else url.searchParams.set('project', String(id));

        router.visit(url.pathname + url.search, { preserveState: true, preserveScroll: true });
    }

    return (
        <div ref={ref} className="relative px-3">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                aria-haspopup="listbox"
                aria-expanded={open}
                title={collapsed ? (active?.name ?? 'All projects') : undefined}
                className={cn(
                    'flex w-full items-center gap-2 rounded-button border border-subtle bg-card py-2 text-left',
                    'transition-colors duration-fast hover:bg-sunken',
                    collapsed ? 'justify-center px-0' : 'px-2.5',
                )}
            >
                <span
                    aria-hidden="true"
                    className="size-2 shrink-0 rounded-pill"
                    style={{ backgroundColor: active?.color ?? 'var(--ink-300)' }}
                />
                {!collapsed && (
                    <>
                        <span className="flex-1 truncate text-base text-ink-900">{active?.name ?? 'All projects'}</span>
                        <ChevronDownIcon size={15} className="shrink-0 text-ink-500" />
                    </>
                )}
            </button>

            {open && (
                <div
                    className={cn(
                        'absolute z-50 mt-1 w-64 animate-scale-in overflow-hidden rounded-card',
                        'border border-subtle bg-card shadow-card',
                        collapsed ? 'left-full top-0 ml-2' : 'left-3 right-3 w-auto',
                    )}
                >
                    <div className="relative border-b border-subtle">
                        <SearchIcon
                            size={15}
                            className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                        />
                        <input
                            autoFocus
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Search projects"
                            aria-label="Search projects"
                            className="h-9 w-full bg-card pl-9 pr-3 text-base text-ink-900 placeholder:text-ink-500"
                        />
                    </div>

                    <ul role="listbox" className="max-h-64 overflow-auto py-1">
                        <li>
                            <button
                                type="button"
                                role="option"
                                aria-selected={activeId === null}
                                onClick={() => choose(null)}
                                className="flex w-full items-center gap-2.5 px-3 py-2 text-left text-base text-ink-900 hover:bg-sunken"
                            >
                                <span aria-hidden="true" className="size-2 shrink-0 rounded-pill bg-ink-300" />
                                <span className="flex-1">All projects</span>
                                {activeId === null && <CheckIcon size={15} className="text-brand" />}
                            </button>
                        </li>

                        {filtered.map((project) => (
                            <li key={project.id}>
                                <button
                                    type="button"
                                    role="option"
                                    aria-selected={project.id === activeId}
                                    onClick={() => choose(project.id)}
                                    className="flex w-full items-center gap-2.5 px-3 py-2 text-left text-base text-ink-900 hover:bg-sunken"
                                >
                                    <span
                                        aria-hidden="true"
                                        className="size-2 shrink-0 rounded-pill"
                                        style={{ backgroundColor: project.color }}
                                    />
                                    <span className="flex-1 truncate">{project.name}</span>
                                    {project.id === activeId && <CheckIcon size={15} className="shrink-0 text-brand" />}
                                </button>
                            </li>
                        ))}

                        {filtered.length === 0 && (
                            <li className="px-3 py-2 text-base text-ink-500">
                                No projects match that. Try a shorter search.
                            </li>
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}
