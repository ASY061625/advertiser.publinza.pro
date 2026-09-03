import { router, useForm } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { cn } from '@shared/lib/cn';
import { Alert, Button, Combobox, Input, MultiSelect, useToast } from '@shared/ui';
import type { LandingPageRow, SitePreview } from '@shared/types/wizard';
import type { ProjectDetail, ProjectSettingsPayload } from '@shared/types/projects';
import { ColorSwatchPicker } from '../../wizard/ColorSwatchPicker';
import { RichBriefEditor } from '../../wizard/RichBriefEditor';
import { SitePreviewCard } from '../../wizard/SitePreviewCard';
import { hostOf } from '../../wizard/validation';
import { LandingPageEditor } from '../LandingPageEditor';
import { DangerZone } from './DangerZone';
import { SettingsNav, SettingsSection } from './SettingsNav';
import { TargetingMatchCard } from './TargetingMatchCard';

interface Props {
    project: ProjectDetail;
    settings: ProjectSettingsPayload;
}

const MAX_NAME = 60;

/** A flag from the ISO code, without shipping an image set. */
function flag(code: string): string {
    return code.toUpperCase().replace(/./g, (char) => String.fromCodePoint(127_397 + char.charCodeAt(0)));
}

/**
 * Everything about a project that is not its posts.
 *
 * Five sections, one form, one save. The footer only exists while something is
 * unsaved, so the page is quiet until there is a decision to make — and every
 * field that actually changed becomes a line in the project's history, which
 * is why the save goes through UpdateProjectSettings rather than a plain
 * update.
 */
export function ProjectSettingsForm({ project, settings }: Props) {
    const { toast } = useToast();
    const [preview, setPreview] = useState<SitePreview | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const lastPreviewed = useRef<string | null>(null);

    const form = useForm({
        ...settings.values,
        landing_pages: settings.values.landing_pages.map((page): LandingPageRow & { id?: number } => ({
            key: page.key,
            id: page.id,
            anchor_text: page.anchor_text,
            url: page.url,
        })),
    });

    // The last saved values, kept typed so Discard can restore them without a
    // round trip through JSON. Compared against by serialising, because
    // Inertia's `isDirty` stays true after a field is changed and changed back.
    type Values = typeof form.data;
    const baseline = useRef<Values>(form.data);
    const dirty = JSON.stringify(form.data) !== JSON.stringify(baseline.current);

    const usage = Object.fromEntries(settings.values.landing_pages.map((page) => [page.key, page.usage]));

    // A colour chosen before this palette existed still has to be visible as
    // the selected one; a picker that cannot show the current value looks like
    // it has none.
    const swatches =
        form.data.color && !settings.options.colors.includes(form.data.color)
            ? [form.data.color, ...settings.options.colors]
            : settings.options.colors;

    const archived = project.isArchived;

    const submit = useCallback(() => {
        if (form.processing || archived) return;

        const snapshot = form.data;

        form.put(`/projects/${project.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                // Optimistic: the form already shows the new values, so a
                // success only has to stop calling them unsaved.
                baseline.current = snapshot;
                toast({ tone: 'success', title: 'Saved' });
            },
            onError: () => {
                // Rolled back to the last saved state rather than left in a
                // half-applied one — the server writes all of it or none of
                // it, and the errors below say which field refused.
                form.setData(baseline.current);
                toast({ tone: 'danger', title: 'Nothing was saved.', description: 'Check the fields marked below.' });
            },
        });
    }, [archived, form, project.id, toast]);

    function discard() {
        form.setData(baseline.current);
        form.clearErrors();
    }

    useUnsavedChangesGuard(dirty);

    // Once, for the address already saved. The field's own fetch is on blur —
    // right for typing, but it would leave the preview blank on arrival, which
    // is exactly when the check is worth having.
    useEffect(() => {
        fetchPreview();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    /**
     * The preview is fetched on blur, not per keystroke: it costs a real
     * outbound request and a half-typed domain is never the one meant.
     */
    function fetchPreview() {
        const url = form.data.website_url.trim();

        if (url === '' || hostOf(url) === null || url === lastPreviewed.current) return;

        lastPreviewed.current = url;
        setPreviewLoading(true);

        void fetch('/projects/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ url }),
        })
            .then((response) => (response.ok ? (response.json() as Promise<SitePreview>) : null))
            .then((result) => setPreview(result))
            .catch(() => setPreview(null))
            .finally(() => setPreviewLoading(false));
    }

    return (
        <div className="pb-24">
            {archived && (
                <Alert tone="info" title="This project is archived.">
                    Its settings are read-only until you restore it. Restore is in the Danger zone below.
                </Alert>
            )}

            <div className={cn('grid grid-cols-1 gap-6 lg:grid-cols-[11rem_minmax(0,1fr)]', archived && 'mt-5')}>
                <SettingsNav />

                <fieldset disabled={archived} className="flex min-w-0 flex-col gap-5">
                    <SettingsSection id="basics" title="Basics">
                        <div>
                            <Input
                                label="Promoted website URL"
                                type="url"
                                value={form.data.website_url}
                                onChange={(event) => form.setData('website_url', event.target.value)}
                                onBlur={fetchPreview}
                                error={form.errors.website_url}
                                hint="The site the links point to. We tidy it into one canonical spelling when you save."
                                required
                            />
                            <SitePreviewCard preview={preview} loading={previewLoading} />
                        </div>

                        <Input
                            label="Project name"
                            value={form.data.name}
                            onChange={(event) => form.setData('name', event.target.value.slice(0, MAX_NAME))}
                            error={form.errors.name}
                            maxLength={MAX_NAME}
                            hint={`${form.data.name.length}/${MAX_NAME}`}
                            required
                        />

                        <Combobox
                            label="Category"
                            placeholder="Search categories…"
                            options={settings.options.categories.map((category) => ({
                                value: String(category.id),
                                label: category.name,
                            }))}
                            value={form.data.category_id === null ? null : String(form.data.category_id)}
                            onChange={(value) => form.setData('category_id', value === null ? null : Number(value))}
                            error={form.errors.category_id}
                            hint="What this project is about. It seeds the catalog's own category filter."
                        />

                        <ColorSwatchPicker
                            colors={swatches}
                            value={form.data.color ?? ''}
                            onChange={(color) => form.setData('color', color)}
                        />
                    </SettingsSection>

                    <SettingsSection id="targeting" title="Targeting">
                        <fieldset>
                            <legend className="text-sm font-medium text-ink-700">Sensitive topics</legend>
                            <p className="mt-0.5 max-w-prose text-sm text-ink-500">
                                We’ll flag sites that don’t accept these topics before you add them to the cart.
                            </p>

                            <div className="mt-2.5 flex flex-wrap gap-2">
                                {settings.options.topics.map((topic) => {
                                    const selected = form.data.sensitive_topic_ids.includes(topic.id);

                                    return (
                                        <button
                                            key={topic.id}
                                            type="button"
                                            aria-pressed={selected}
                                            onClick={() =>
                                                form.setData(
                                                    'sensitive_topic_ids',
                                                    selected
                                                        ? form.data.sensitive_topic_ids.filter((id) => id !== topic.id)
                                                        : [...form.data.sensitive_topic_ids, topic.id],
                                                )
                                            }
                                            className={cn(
                                                'rounded-pill border px-3 py-1.5 text-sm font-medium',
                                                'transition-colors duration-fast ease-standard',
                                                selected
                                                    ? 'border-brand bg-brand-subtle text-brand'
                                                    : 'border-subtle bg-card text-ink-700 hover:bg-sunken',
                                            )}
                                        >
                                            {topic.name}
                                        </button>
                                    );
                                })}
                            </div>
                        </fieldset>

                        <SelectAll
                            label="Countries"
                            all={settings.options.countries.map((country) => country.id)}
                            value={form.data.country_ids}
                            onChange={(ids) => form.setData('country_ids', ids)}
                        >
                            <MultiSelect
                                label="Countries"
                                hideLabel
                                placeholder="Any country"
                                options={settings.options.countries.map((country) => ({
                                    value: String(country.id),
                                    label: `${flag(country.code)}  ${country.name}`,
                                }))}
                                value={form.data.country_ids.map(String)}
                                onChange={(value) => form.setData('country_ids', value.map(Number))}
                                hint="Where the audience is. Leave blank for anywhere."
                            />
                        </SelectAll>

                        <SelectAll
                            label="Languages"
                            all={settings.options.languages.map((language) => language.id)}
                            value={form.data.language_ids}
                            onChange={(ids) => form.setData('language_ids', ids)}
                        >
                            <MultiSelect
                                label="Languages"
                                hideLabel
                                placeholder="Any language"
                                options={settings.options.languages.map((language) => ({
                                    value: String(language.id),
                                    label: language.name,
                                }))}
                                value={form.data.language_ids.map(String)}
                                onChange={(value) => form.setData('language_ids', value.map(Number))}
                                hint="What the article should be written in. Leave blank for any."
                            />
                        </SelectAll>

                        <TargetingMatchCard
                            projectId={project.id}
                            topicIds={form.data.sensitive_topic_ids}
                            countryIds={form.data.country_ids}
                            languageIds={form.data.language_ids}
                        />
                    </SettingsSection>

                    <SettingsSection id="brief" title="Publisher brief">
                        <div>
                            <RichBriefEditor
                                id="project-task"
                                value={form.data.publisher_task}
                                onChange={(html) => form.setData('publisher_task', html)}
                                error={form.errors.publisher_task}
                                hint="Tone, things to avoid, anything a writer should know. Bold, italic, lists and links only."
                            />

                            <p className="mt-1.5 text-sm text-ink-500">
                                This is the project’s default brief, sent to the writer with every post. A folder can
                                override it for the pages inside it.
                            </p>
                        </div>
                    </SettingsSection>

                    <SettingsSection
                        id="landing-pages"
                        title="Landing pages"
                        description={
                            settings.folderName === null
                                ? undefined
                                : `The pages in “${settings.folderName}”, this project’s default folder. Other folders keep their own, edited from the General tab.`
                        }
                    >
                        {form.errors.landing_pages && (
                            <Alert tone="danger" title="Nothing was saved.">
                                {form.errors.landing_pages}
                            </Alert>
                        )}

                        <LandingPageEditor
                            rows={form.data.landing_pages}
                            onChange={(rows) => form.setData('landing_pages', rows)}
                            websiteUrl={form.data.website_url}
                            errors={form.errors}
                            usage={usage}
                            heading={<span className="sr-only">Landing pages</span>}
                        />
                    </SettingsSection>

                    <SettingsSection
                        id="danger"
                        title="Danger zone"
                        description="Two ways to stop using this project, and the difference between them."
                        danger
                    >
                        <DangerZone
                            project={project}
                            blockingPosts={settings.blockingPosts}
                            retentionDays={settings.retentionDays}
                            // Archiving or deleting is a deliberate navigation;
                            // the guard must not ask about edits that are about
                            // to stop mattering.
                            onLeave={() => {
                                baseline.current = form.data;
                            }}
                        />
                    </SettingsSection>
                </fieldset>
            </div>

            {/* Only while something is unsaved: a bar that is always there is
                furniture, and one that appears is an answer to "did that
                register?". */}
            {dirty && !archived && (
                <div
                    className={cn(
                        'sticky bottom-0 z-20 -mx-4 -mb-6 mt-5 flex flex-wrap items-center justify-between gap-3',
                        'border-t border-subtle bg-card px-4 py-3 lg:-mx-6 lg:px-6',
                    )}
                >
                    <span className="text-sm text-ink-500">Unsaved changes</span>

                    <div className="flex items-center gap-2">
                        <Button variant="ghost" type="button" onClick={discard} disabled={form.processing}>
                            Discard
                        </Button>
                        <Button type="button" onClick={submit} loading={form.processing}>
                            Save changes
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

/** The label and its select-all, which every long multi-select here wants. */
function SelectAll({
    label,
    all,
    value,
    onChange,
    children,
}: {
    label: string;
    all: number[];
    value: number[];
    onChange: (ids: number[]) => void;
    children: React.ReactNode;
}) {
    const allSelected = all.length > 0 && value.length === all.length;

    return (
        <div>
            <div className="flex items-end justify-between gap-3">
                <span className="text-sm font-medium text-ink-700">{label}</span>
                <button
                    type="button"
                    onClick={() => onChange(allSelected ? [] : all)}
                    className="text-sm font-medium text-brand hover:underline"
                >
                    {allSelected ? 'Clear all' : 'Select all'}
                </button>
            </div>

            <div className="mt-1.5">{children}</div>
        </div>
    );
}

/**
 * The browser's own dialog for a hard navigation, Inertia's hook for a soft
 * one. Both are needed: beforeunload does nothing for a client-side visit, and
 * Inertia's hook does nothing for a closed tab.
 */
function useUnsavedChangesGuard(dirty: boolean): void {
    useEffect(() => {
        if (!dirty) return;

        function onBeforeUnload(event: BeforeUnloadEvent) {
            event.preventDefault();
            event.returnValue = '';
        }

        window.addEventListener('beforeunload', onBeforeUnload);

        const stop = router.on('before', (event) => {
            // The save itself is a visit. Only navigations away are questioned.
            if (event.detail.visit.method !== 'get') return;

            if (!window.confirm('You have unsaved changes to these settings. Leave without saving?')) {
                event.preventDefault();
            }
        });

        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            stop();
        };
    }, [dirty]);
}
