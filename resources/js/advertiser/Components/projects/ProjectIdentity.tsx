import { cn } from '@shared/lib/cn';
import type { ProjectRow } from '@shared/types/projects';

/**
 * The dot, name, promoted URL and category — one project's identity.
 *
 * The dot's colour is derived from the project id rather than stored, because
 * nobody asked to pick one and a column nobody sets is a column that is always
 * the default. Derived means stable: the same project is the same colour on
 * every screen, every session, without a migration or a preference to keep.
 *
 * It is decoration, not data. Two projects can share a hue and nothing is lost,
 * because the name is right beside it — which is why the palette is picked for
 * pleasantness rather than run through a categorical-separation check.
 */
const DOTS = [
    'var(--brand-blue)',
    'var(--teal)',
    'var(--status-review-fg)',
    'var(--gold)',
    'var(--status-progress-fg)',
    'var(--success)',
];

export function projectDot(id: number): string {
    return DOTS[id % DOTS.length] ?? DOTS[0]!;
}

export function ProjectIdentity({ project, size = 'row' }: { project: ProjectRow; size?: 'row' | 'card' }) {
    return (
        <span className="flex min-w-0 items-start gap-2.5">
            <span
                aria-hidden="true"
                className="mt-1.5 size-2.5 shrink-0 rounded-pill"
                style={{ backgroundColor: projectDot(project.id) }}
            />

            <span className="flex min-w-0 flex-col gap-0.5">
                <span className="flex flex-wrap items-center gap-2">
                    <span
                        className={cn(
                            'truncate font-medium text-ink-900',
                            size === 'card' ? 'font-sora text-md' : 'text-sm',
                        )}
                    >
                        {project.name}
                    </span>

                    {project.category && (
                        <span className="shrink-0 rounded-pill bg-sunken px-2 py-0.5 text-xs text-ink-500">
                            {project.category}
                        </span>
                    )}

                    {project.isArchived && (
                        <span className="shrink-0 rounded-pill bg-status-frozen-bg px-2 py-0.5 text-xs font-medium text-status-frozen-fg">
                            Archived
                        </span>
                    )}
                </span>

                <a
                    href={project.websiteUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    onClick={(event) => event.stopPropagation()}
                    title={project.websiteUrl}
                    className="inline-flex items-center gap-1 truncate text-xs text-ink-500 hover:text-brand hover:underline"
                >
                    <span className="truncate">{strip(project.websiteUrl)}</span>
                    <ExternalLinkIcon />
                </a>
            </span>
        </span>
    );
}

/** The scheme is noise in a list of URLs; the host and path are the content. */
function strip(url: string): string {
    return url.replace(/^https?:\/\//, '').replace(/\/$/, '');
}

function ExternalLinkIcon() {
    return (
        <svg
            width={10}
            height={10}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2.2}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            className="shrink-0"
        >
            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
            <path d="M15 3h6v6" />
            <path d="M10 14 21 3" />
        </svg>
    );
}
