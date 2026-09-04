import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Alert, Button, Select, XIcon } from '@shared/ui';
import type { CatalogProject } from '@shared/types/catalog';

const DISMISSED_KEY = 'catalog:browse-banner-dismissed';

interface Props {
    project: CatalogProject | null;
    projects: { id: number; name: string; color: string | null }[];
    /** Everything else in the query string, so switching keeps the filters. */
    query: Record<string, unknown>;
}

/**
 * Which mode the catalog is in, and how to change it.
 *
 * Browse mode gets a selector and an explanation; buying mode gets a pill and a
 * way back. The two are the same control at different sizes, because choosing a
 * project is the single most consequential thing on this page — it is what turns
 * a price list into a shop.
 */
export function ProjectBar({ project, projects, query }: Props) {
    const [dismissed, setDismissed] = useState(true);

    // Read after mount rather than during render: localStorage throws in some
    // contexts, and a banner is not worth a blank page.
    useEffect(() => {
        try {
            setDismissed(window.localStorage.getItem(DISMISSED_KEY) === '1');
        } catch {
            setDismissed(false);
        }
    }, []);

    function choose(id: string) {
        router.get('/catalog', { ...query, project: id || undefined }, { preserveState: true, preserveScroll: true });
    }

    if (project !== null) {
        return (
            <div className="flex flex-wrap items-center gap-2">
                <span className="inline-flex items-center gap-2 rounded-pill border border-subtle bg-card py-1 pl-2 pr-3 text-sm">
                    <span
                        aria-hidden="true"
                        className="size-2 rounded-pill"
                        style={{ backgroundColor: project.color ?? 'var(--brand-blue)' }}
                    />
                    <span className="text-ink-500">Buying for</span>
                    <span className="font-medium text-ink-900">{project.name}</span>
                </span>

                <button
                    type="button"
                    onClick={() => choose('')}
                    className="text-sm font-medium text-brand hover:underline"
                >
                    Change
                </button>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-end gap-3 rounded-card border border-subtle bg-card p-4 shadow-card">
                <div className="min-w-[240px] flex-1">
                    <Select
                        label="Buying for"
                        value=""
                        onChange={(event) => choose(event.target.value)}
                        options={[
                            { value: '', label: 'Choose a project…' },
                            ...projects.map((p) => ({ value: String(p.id), label: p.name })),
                        ]}
                        hint={
                            projects.length === 0
                                ? 'You have no active projects yet.'
                                : 'Pick one to enable buying and match the catalog to its targeting.'
                        }
                    />
                </div>

                {projects.length === 0 && (
                    <Link href="/projects/create">
                        <Button variant="secondary">Create a project</Button>
                    </Link>
                )}
            </div>

            {!dismissed && (
                <Alert tone="info" title="You are browsing, not buying.">
                    <span className="flex flex-wrap items-start justify-between gap-3">
                        <span>
                            Choosing a project turns on “Add to cart” and starts the catalog from that project’s
                            countries, languages and category — all as filters you can change or remove.
                        </span>
                        <button
                            type="button"
                            aria-label="Dismiss"
                            className="shrink-0 rounded-button p-1 text-ink-500 hover:text-ink-900"
                            onClick={() => {
                                setDismissed(true);
                                try {
                                    window.localStorage.setItem(DISMISSED_KEY, '1');
                                } catch {
                                    // Dismissed for this visit either way.
                                }
                            }}
                        >
                            <XIcon size={14} />
                        </button>
                    </span>
                </Alert>
            )}
        </div>
    );
}
