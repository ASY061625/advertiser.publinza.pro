import { useEffect, useState } from 'react';
import { cn } from '@shared/lib/cn';

export interface SettingsSectionId {
    id: string;
    label: string;
}

export const SETTINGS_SECTIONS: SettingsSectionId[] = [
    { id: 'basics', label: 'Basics' },
    { id: 'targeting', label: 'Targeting' },
    { id: 'brief', label: 'Publisher brief' },
    { id: 'landing-pages', label: 'Landing pages' },
    { id: 'danger', label: 'Danger zone' },
];

/**
 * The in-page nav down the left of the settings form.
 *
 * Anchors, not tabs: the sections are one form and one save, and hiding four
 * of them behind tabs would let someone submit changes they cannot see. The
 * nav is a way to reach a section quickly and a map of what the page contains.
 *
 * The active item follows the scroll rather than the click, so it stays right
 * when someone scrolls past a section instead of jumping to it.
 */
export function SettingsNav() {
    const [active, setActive] = useState(SETTINGS_SECTIONS[0]!.id);

    useEffect(() => {
        const sections = SETTINGS_SECTIONS.map(({ id }) => document.getElementById(id)).filter(
            (element): element is HTMLElement => element !== null,
        );

        if (sections.length === 0) return;

        const observer = new IntersectionObserver(
            (entries) => {
                // The topmost section currently crossing the band below the
                // sticky header. Taking the first intersecting entry rather
                // than the most visible one stops the highlight flickering
                // between two neighbours on a slow scroll.
                const visible = entries
                    .filter((entry) => entry.isIntersecting)
                    .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);

                if (visible[0]) setActive(visible[0].target.id);
            },
            { rootMargin: '-96px 0px -60% 0px', threshold: 0 },
        );

        sections.forEach((section) => observer.observe(section));

        return () => observer.disconnect();
    }, []);

    return (
        <nav aria-label="Settings sections" className="sticky top-[calc(var(--header-height,3.5rem)+1.25rem)]">
            <ul className="flex flex-row gap-1 overflow-x-auto lg:flex-col lg:overflow-visible">
                {SETTINGS_SECTIONS.map((section) => {
                    const current = section.id === active;

                    return (
                        <li key={section.id} className="shrink-0">
                            <a
                                href={`#${section.id}`}
                                aria-current={current ? 'true' : undefined}
                                className={cn(
                                    'block whitespace-nowrap rounded-button px-3 py-2 text-sm',
                                    'transition-colors duration-fast ease-standard',
                                    current
                                        ? 'bg-brand-subtle font-medium text-brand'
                                        : 'text-ink-500 hover:bg-sunken hover:text-ink-900',
                                    section.id === 'danger' && !current && 'text-danger/80 hover:text-danger',
                                )}
                            >
                                {section.label}
                            </a>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

/** One section of the form: an anchor target with a heading above it. */
export function SettingsSection({
    id,
    title,
    description,
    danger = false,
    children,
}: {
    id: string;
    title: string;
    description?: string;
    danger?: boolean;
    children: React.ReactNode;
}) {
    return (
        <section
            id={id}
            aria-labelledby={`${id}-heading`}
            // Clears the sticky app header when an anchor jumps here.
            className="scroll-mt-24"
        >
            <div
                className={cn(
                    'rounded-card border bg-card p-5 shadow-card',
                    danger ? 'border-danger' : 'border-subtle',
                )}
            >
                <h2
                    id={`${id}-heading`}
                    className={cn('font-sora text-md font-semibold', danger ? 'text-danger' : 'text-ink-900')}
                >
                    {title}
                </h2>

                {description && <p className="mt-1 max-w-prose text-sm text-ink-500">{description}</p>}

                <div className="mt-4 flex flex-col gap-5">{children}</div>
            </div>
        </section>
    );
}
