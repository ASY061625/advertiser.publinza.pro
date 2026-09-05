import { useMemo } from 'react';
import { Input, Select } from '@shared/ui';
import type { PostWizardState, WizardProject } from '@shared/types/postWizard';

interface Props {
    state: PostWizardState;
    patch: (changes: Partial<PostWizardState>) => void;
    projects: WizardProject[];
    /** Set when launched from inside a project: the choice is already made. */
    lockedProject: WizardProject | null;
}

const MANUAL = 'manual';

/**
 * Where the post goes, and what it points at.
 *
 * Project first because everything downstream narrows from it — the folders,
 * the saved landing pages, the brief the publisher step prefills, and the
 * targeting that pre-filters the site picker. Asking for it later would mean
 * asking the other three questions twice.
 */
export function StepProject({ state, patch, projects, lockedProject }: Props) {
    const project = lockedProject ?? projects.find((entry) => String(entry.id) === state.projectId) ?? null;

    // Landing pages belong to a folder. Choosing a folder and then being
    // offered another folder's URLs is how a post ends up pointing somewhere
    // nobody meant.
    const pages = useMemo(
        () =>
            (project?.landingPages ?? []).filter(
                (page) => state.folderId === '' || String(page.folderId ?? '') === state.folderId,
            ),
        [project, state.folderId],
    );

    const manual = state.landingPageId === MANUAL || (state.landingPageId === '' && state.targetUrl !== '');
    const mismatch = manual ? domainMismatch(state.targetUrl, project?.websiteUrl ?? null) : null;

    return (
        <div className="flex flex-col gap-4">
            {lockedProject !== null ? (
                <div>
                    <p className="mb-1 text-sm font-medium text-ink-700">Project</p>
                    {/* Read-only rather than a disabled select: the choice was
                        made by where the wizard was opened from, and a control
                        that cannot be operated is worse than no control. */}
                    <p className="inline-flex items-center gap-2 rounded-pill bg-sunken px-3 py-1.5">
                        <span
                            aria-hidden="true"
                            className="size-2 shrink-0 rounded-full"
                            style={{ backgroundColor: lockedProject.color ?? 'var(--ink-300)' }}
                        />
                        <span className="font-medium text-ink-900">{lockedProject.name}</span>
                    </p>
                </div>
            ) : (
                <Select
                    label="Project"
                    required
                    value={state.projectId}
                    onChange={(event) =>
                        // Folder and landing page belong to the old project, so
                        // they cannot survive the change. Left set, the post
                        // would file into a folder it is not part of.
                        patch({
                            projectId: event.target.value,
                            folderId: '',
                            landingPageId: '',
                            anchorText: '',
                            targetUrl: '',
                        })
                    }
                    options={[
                        { value: '', label: 'Choose a project…' },
                        ...projects.map((entry) => ({ value: String(entry.id), label: entry.name })),
                    ]}
                />
            )}

            {project !== null && project.folders.length > 0 && (
                <Select
                    label="Folder"
                    value={state.folderId}
                    hint="Groups this post with the rest of a campaign."
                    onChange={(event) => patch({ folderId: event.target.value, landingPageId: '' })}
                    options={[
                        { value: '', label: 'No folder' },
                        ...project.folders.map((folder) => ({
                            value: String(folder.id),
                            label: folder.name,
                        })),
                    ]}
                />
            )}

            {project !== null && (
                <fieldset className="flex flex-col gap-3">
                    <legend className="text-sm font-medium text-ink-700">Landing page</legend>

                    {pages.length > 0 && (
                        <Select
                            label="Saved pages"
                            hideLabel
                            value={manual ? MANUAL : state.landingPageId}
                            onChange={(event) => {
                                const chosen = pages.find((page) => String(page.id) === event.target.value);

                                patch(
                                    chosen === undefined
                                        ? { landingPageId: MANUAL, anchorText: '', targetUrl: '' }
                                        : {
                                              landingPageId: String(chosen.id),
                                              anchorText: chosen.anchorText,
                                              targetUrl: chosen.url,
                                          },
                                );
                            }}
                            options={[
                                ...pages.map((page) => ({
                                    value: String(page.id),
                                    label: `${page.anchorText} — ${page.url}`,
                                })),
                                { value: MANUAL, label: 'Use a different page…' },
                            ]}
                        />
                    )}

                    {(manual || pages.length === 0) && (
                        <div className="flex flex-col gap-3">
                            <Input
                                label="Anchor text"
                                required
                                value={state.anchorText}
                                onChange={(event) => patch({ anchorText: event.target.value })}
                                placeholder="invoicing software"
                            />

                            <div>
                                <Input
                                    label="Target URL"
                                    type="url"
                                    required
                                    value={state.targetUrl}
                                    onChange={(event) =>
                                        patch({ targetUrl: event.target.value, landingPageId: MANUAL })
                                    }
                                    placeholder={project.websiteUrl ?? 'https://'}
                                />

                                {/* A warning, not an error: it is rendered in
                                    the warning tone and blocks nothing, because
                                    pointing at a microsite or a partner page is
                                    a real thing to want. Colouring it red would
                                    be the form claiming to know better. */}
                                {mismatch !== null && (
                                    <p className="mt-1 text-sm text-warning">{mismatch}</p>
                                )}
                            </div>
                        </div>
                    )}
                </fieldset>
            )}
        </div>
    );
}

/**
 * Whether a typed URL points somewhere other than the project's own site.
 *
 * A warning rather than a block — an advertiser may legitimately point a
 * placement at a landing page on a separate domain, a campaign microsite or a
 * partner's page — but nine times out of ten a different domain here is a
 * pasted URL from the wrong tab, and it is expensive to find out after the
 * publisher has run it.
 */
export function domainMismatch(url: string, projectUrl: string | null): string | null {
    if (url.trim() === '' || projectUrl === null) return null;

    const target = hostOf(url);
    const expected = hostOf(projectUrl);

    if (target === null || expected === null || target === expected) return null;

    return `This points at ${target}, not ${expected}. That is allowed — check it is what you meant.`;
}

/** The registrable-ish host: "www." dropped, so a www URL matches a bare one. */
function hostOf(value: string): string | null {
    try {
        const withScheme = /^https?:\/\//i.test(value) ? value : `https://${value}`;

        return new URL(withScheme).hostname.replace(/^www\./i, '').toLowerCase();
    } catch {
        return null;
    }
}
