import { Head, router } from '@inertiajs/react';
import { AppShell } from '../Layouts/AppShell';
import { CheckIcon, EmptyState, SparkleIcon, Tabs } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { date } from '@shared/lib/format';
import type { ChangelogMonth, ChangelogTypeName } from '@shared/types/notifications';
import { ChangelogChip } from '../Components/shell/ChangelogChip';

interface Props {
    type: ChangelogTypeName | null;
    months: ChangelogMonth[];
    counts: Record<string, number>;
}

const FILTERS: { id: string; label: string }[] = [
    { id: 'all', label: 'Everything' },
    { id: 'new', label: 'New' },
    { id: 'improved', label: 'Improved' },
    { id: 'fixed', label: 'Fixed' },
];

export default function WhatsNew({ type, months, counts }: Props) {
    const active = type ?? 'all';

    return (
        <AppShell title="What's new" crumbs={[{ label: "What's new" }]}>
            <Head title="What's new" />

            <div className="flex min-w-0 flex-col gap-5">
                <header>
                    <h1 className="font-sora text-xl font-semibold text-ink-900">What&apos;s new</h1>
                    <p className="mt-1 text-sm text-ink-500">Everything we have changed, newest first.</p>
                </header>

                <Tabs
                    scrollable
                    items={FILTERS.map((filter) => ({ ...filter, count: counts[filter.id] ?? 0 }))}
                    value={active}
                    onChange={(next) =>
                        router.get(
                            '/whats-new',
                            next === 'all' ? {} : { type: next },
                            { preserveScroll: true, preserveState: false, replace: true },
                        )
                    }
                />

                {months.length === 0 ? (
                    <EmptyState
                        illustration={<SparkleIcon size={22} />}
                        direction="Nothing here yet"
                        body={
                            active === 'all'
                                ? 'We will note every change to Publinza here.'
                                : 'Nothing of that kind has been published. Try another filter.'
                        }
                    />
                ) : (
                    <div className="flex max-w-2xl flex-col gap-8">
                        {months.map((month) => (
                            <section key={month.key} className="flex flex-col gap-4">
                                {/*
                                    Scoped to its own section, not a shared
                                    parent. Sibling `sticky top-0` elements
                                    under one parent all pin at the same place
                                    and stack up as you scroll.
                                */}
                                <h2 className="sticky top-header z-10 -mx-1 bg-canvas px-1 py-2 font-sora text-md font-semibold text-ink-900">
                                    {month.label}
                                </h2>

                                <ol className="flex flex-col gap-5">
                                    {month.entries.map((entry) => (
                                        <li
                                            key={entry.id}
                                            id={entry.anchor}
                                            className={cn(
                                                'card scroll-mt-24 p-6',
                                                entry.isMajor && 'border-brand/40',
                                            )}
                                        >
                                            <div className="flex flex-wrap items-center gap-2.5">
                                                <ChangelogChip type={entry.type} label={entry.typeLabel} />

                                                {entry.isMajor && (
                                                    <span className="rounded-pill bg-brand-subtle px-2.5 py-1 text-xs font-medium text-brand">
                                                        Major
                                                    </span>
                                                )}

                                                {entry.publishedAt && (
                                                    <time
                                                        dateTime={entry.publishedAt}
                                                        className="text-sm text-ink-500"
                                                    >
                                                        {date(entry.publishedAt)}
                                                    </time>
                                                )}

                                                {/* An anchor per entry, so one
                                                    change can be linked to on
                                                    its own. */}
                                                <a
                                                    href={`#${entry.anchor}`}
                                                    aria-label={`Link to “${entry.title}”`}
                                                    className="ml-auto text-sm text-ink-500 underline hover:text-brand"
                                                >
                                                    #
                                                </a>
                                            </div>

                                            <h3 className="mt-3 font-sora text-md font-semibold text-ink-900">
                                                {entry.title}
                                            </h3>

                                            {entry.imageUrl !== null && (
                                                <img
                                                    src={entry.imageUrl}
                                                    alt=""
                                                    loading="lazy"
                                                    className="mt-4 w-full rounded-card border border-subtle"
                                                />
                                            )}

                                            {/* Sanitised server-side against a
                                                fixed tag set — see
                                                ChangelogHtml. */}
                                            <div
                                                className="prose-changelog mt-2"
                                                dangerouslySetInnerHTML={{ __html: entry.body }}
                                            />
                                        </li>
                                    ))}
                                </ol>
                            </section>
                        ))}
                    </div>
                )}

                <p className="flex items-center gap-2 text-sm text-ink-500">
                    <CheckIcon size={14} className="text-teal" />
                    You are up to date.
                </p>
            </div>
        </AppShell>
    );
}
