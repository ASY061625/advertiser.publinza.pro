import { Head, Link, useForm } from '@inertiajs/react';
import { AppShell } from '../../Layouts/AppShell';
import { Button, Input, Select, Textarea } from '@shared/ui';

interface Props {
    categories: { id: number; name: string }[];
}

/**
 * The create form.
 *
 * Deliberately the four fields the `projects` table actually has. Targeting —
 * countries, languages, sensitive topics — lives on the project's own screen
 * once it exists, because none of it is needed to make the project real and
 * asking for it here would put a wall in front of the first thing a new
 * advertiser does.
 */
export default function ProjectsCreate({ categories }: Props) {
    const form = useForm({
        name: '',
        website_url: '',
        category_id: '',
        publisher_task: '',
    });

    return (
        <AppShell title="Projects" crumbs={[{ label: 'My projects', href: '/projects' }, { label: 'New project' }]}>
            <Head title="Create project" />

            <div className="mx-auto max-w-xl">
                <h1 className="font-sora text-xl font-semibold text-ink-900">Create project</h1>
                <p className="mt-1 text-sm text-ink-500">
                    A project is the site you&rsquo;re promoting. Sites, posts and spend are all tracked per project.
                </p>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/projects');
                    }}
                    className="mt-6 flex flex-col gap-4 rounded-card border border-subtle bg-card p-5 shadow-card"
                >
                    <Input
                        label="Project name"
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
                        error={form.errors.name}
                        hint="What you will recognise it by — usually the site or the campaign."
                        maxLength={120}
                        required
                    />

                    <Input
                        label="Website you are promoting"
                        type="url"
                        inputMode="url"
                        value={form.data.website_url}
                        onChange={(event) => form.setData('website_url', event.target.value)}
                        error={form.errors.website_url}
                        placeholder="https://example.com"
                        required
                    />

                    <Select
                        label="Category"
                        value={form.data.category_id}
                        onChange={(event) => form.setData('category_id', event.target.value)}
                        error={form.errors.category_id}
                        options={[
                            { value: '', label: 'No category' },
                            ...categories.map((category) => ({
                                value: String(category.id),
                                label: category.name,
                            })),
                        ]}
                        hint="Used to suggest sites that fit."
                    />

                    <Textarea
                        label="Notes for writers"
                        value={form.data.publisher_task}
                        onChange={(event) => form.setData('publisher_task', event.target.value)}
                        error={form.errors.publisher_task}
                        rows={4}
                        hint="Optional. Tone, things to avoid, anything a writer should know. You can change it later."
                    />

                    <div className="flex justify-end gap-2 border-t border-subtle pt-4">
                        <Link href="/projects">
                            <Button variant="secondary" type="button">
                                Cancel
                            </Button>
                        </Link>
                        <Button type="submit" loading={form.processing}>
                            Create project
                        </Button>
                    </div>
                </form>
            </div>
        </AppShell>
    );
}
