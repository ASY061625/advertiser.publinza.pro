import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { cn } from '@shared/lib/cn';
import { AppShell } from '../../Layouts/AppShell';
import { Button, Input, Select, Textarea } from '@shared/ui';
import { date } from '@shared/lib/format';

interface ProjectDetail {
    id: number;
    name: string;
    website_url: string;
    status: string;
    publisher_task: string | null;
    category_id: number | null;
    category: { id: number; name: string } | null;
    folders: { id: number; name: string }[];
    created_at: string | null;
}

interface Props {
    project: ProjectDetail;
    categories: { id: number; name: string }[];
    /** Flashed by the create wizard, read once. */
    justCreated?: boolean;
}

/**
 * One project: General and Settings.
 *
 * Deliberately small. The full project screen — targeting, landing pages,
 * competitors, folders — is its own piece of work; this covers what /projects
 * links to so a row click and the Edit action both land somewhere real rather
 * than on a page that does not exist. Expect the tab strip to grow.
 */
export default function ProjectsShow({ project, categories, justCreated = false }: Props) {
    const initialTab = new URLSearchParams(window.location.search).get('tab') === 'settings' ? 'settings' : 'general';
    const [tab, setTab] = useState<'general' | 'settings'>(initialTab);

    return (
        <AppShell title="Projects" crumbs={[{ label: 'My projects', href: '/projects' }, { label: project.name }]}>
            <Head title={project.name} />

            <header className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="font-sora text-xl font-semibold text-ink-900">{project.name}</h1>
                    <a
                        href={project.website_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="mt-1 inline-block text-sm text-ink-500 hover:text-brand hover:underline"
                    >
                        {project.website_url.replace(/^https?:\/\//, '')}
                    </a>
                </div>

                <Link href={`/posts?projects[]=${project.id}`}>
                    <Button variant="secondary">See posts</Button>
                </Link>
            </header>

            <div role="tablist" className="mt-5 flex gap-1 border-b border-subtle">
                {(
                    [
                        ['general', 'General'],
                        ['settings', 'Settings'],
                    ] as const
                ).map(([id, label]) => (
                    <button
                        key={id}
                        type="button"
                        role="tab"
                        aria-selected={tab === id}
                        onClick={() => setTab(id)}
                        className={cn(
                            'border-b-2 px-3 pb-2.5 pt-2 text-base font-medium transition-colors duration-fast',
                            tab === id
                                ? 'border-brand text-brand'
                                : 'border-transparent text-ink-500 hover:text-ink-700',
                        )}
                    >
                        {label}
                    </button>
                ))}
            </div>

            <div className="mt-5 max-w-xl">
                {tab === 'general' ? (
                    <>
                        {justCreated && (
                            <div className="mb-4 rounded-card border border-brand bg-brand-subtle p-4">
                                <h2 className="font-sora text-md font-semibold text-ink-900">
                                    {project.name} is ready.
                                </h2>
                                <p className="mt-1 text-sm text-ink-700">
                                    Next: pick the sites you want to be published on. Every one in the catalog is a site
                                    we own and run, so a placement you order goes live.
                                </p>
                                <Link href="/catalog" className="mt-3 inline-block">
                                    <Button>Find a website</Button>
                                </Link>
                            </div>
                        )}

                        <dl className="grid grid-cols-[minmax(0,8rem)_1fr] gap-x-4 gap-y-3 rounded-card border border-subtle bg-card p-5 text-sm shadow-card">
                            {(
                                [
                                    ['Status', project.status === 'archived' ? 'Archived' : 'Active'],
                                    ['Category', project.category?.name ?? '—'],
                                    ['Folders', project.folders.map((f) => f.name).join(', ') || '—'],
                                    ['Created', project.created_at ? date(project.created_at) : '—'],
                                ] as [string, string][]
                            ).map(([label, value]) => (
                                <div key={label} className="contents">
                                    <dt className="text-ink-500">{label}</dt>
                                    <dd className="whitespace-pre-wrap text-ink-900">{value}</dd>
                                </div>
                            ))}

                            <div className="contents">
                                <dt className="text-ink-500">Notes for writers</dt>
                                <dd className="min-w-0 text-ink-900">
                                    {project.publisher_task ? (
                                        // Authored in the wizard's editor and put
                                        // through HtmlSanitizer before it was
                                        // stored; see ProjectWizardData.
                                        <div
                                            className="prose-publinza max-w-none"
                                            dangerouslySetInnerHTML={{ __html: project.publisher_task }}
                                        />
                                    ) : (
                                        '—'
                                    )}
                                </dd>
                            </div>
                        </dl>
                    </>
                ) : (
                    <SettingsForm project={project} categories={categories} />
                )}
            </div>
        </AppShell>
    );
}

function SettingsForm({ project, categories }: Props) {
    const form = useForm({
        name: project.name,
        website_url: project.website_url,
        category_id: project.category_id === null ? '' : String(project.category_id),
        publisher_task: project.publisher_task ?? '',
    });

    const archived = project.status === 'archived';

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(`/projects/${project.id}`, { preserveScroll: true });
            }}
            className="flex flex-col gap-4 rounded-card border border-subtle bg-card p-5 shadow-card"
        >
            {archived && (
                <p className="rounded-card bg-sunken px-3 py-2 text-sm text-ink-700">
                    This project is archived and read-only. Restore it from the projects list to edit it.
                </p>
            )}

            <Input
                label="Project name"
                value={form.data.name}
                onChange={(event) => form.setData('name', event.target.value)}
                error={form.errors.name}
                disabled={archived}
                required
            />

            <Input
                label="Website you are promoting"
                type="url"
                value={form.data.website_url}
                onChange={(event) => form.setData('website_url', event.target.value)}
                error={form.errors.website_url}
                disabled={archived}
                required
            />

            <Select
                label="Category"
                value={form.data.category_id}
                onChange={(event) => form.setData('category_id', event.target.value)}
                error={form.errors.category_id}
                disabled={archived}
                options={[
                    { value: '', label: 'No category' },
                    ...categories.map((c) => ({ value: String(c.id), label: c.name })),
                ]}
            />

            <Textarea
                label="Notes for writers"
                value={form.data.publisher_task}
                onChange={(event) => form.setData('publisher_task', event.target.value)}
                error={form.errors.publisher_task}
                disabled={archived}
                rows={4}
            />

            <div className="flex justify-end border-t border-subtle pt-4">
                <Button type="submit" loading={form.processing} disabled={archived}>
                    Save changes
                </Button>
            </div>
        </form>
    );
}
