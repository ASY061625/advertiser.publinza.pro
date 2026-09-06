import { Head, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { AppShell } from '../../Layouts/AppShell';
import { Tabs, useToast } from '@shared/ui';
import type { AdvertiserSharedProps } from '@shared/types';
import type { ListTab, ListsPageProps } from '@shared/types/lists';
import { BlacklistTab } from '../../Components/lists/BlacklistTab';
import { FavoritesTab } from '../../Components/lists/FavoritesTab';
import { useListQuery } from '../../Components/lists/ListToolbar';
import { WishlistTab } from '../../Components/lists/WishlistTab';

const TAB_LABELS: { id: ListTab; label: string }[] = [
    { id: 'favorites', label: 'Favorites' },
    { id: 'wishlist', label: 'Wishlist' },
    { id: 'blacklist', label: 'Blacklist' },
];

/**
 * The three lists an advertiser keeps about sites.
 *
 * One page rather than three, because they are one decision seen from three
 * angles — a site is saved, planned or refused — and moving between them is the
 * most common thing anybody does here. Three routes would have made that a
 * navigation.
 *
 * The tab is in the query string, so a view is a link: the header's heart
 * points at ?tab=favorites and lands on it.
 */
export default function ListsIndex({
    tab,
    filters,
    rows,
    counts,
    wishlists,
    summary,
    ranges,
    categories,
    projects,
    sorts,
}: ListsPageProps) {
    const page = usePage<AdvertiserSharedProps>();
    const { toast } = useToast();
    const [selected, setSelected] = useState<Set<number>>(new Set());

    // The project scope the sidebar is already carrying, if any. A second
    // control saying the same thing invites the two to disagree.
    const scopedProject = useMemo(() => {
        const value = new URLSearchParams(page.url.split('?')[1] ?? '').get('project');

        return value === null ? null : Number(value);
    }, [page.url]);

    const [chosenProject, setChosenProject] = useState<number | null>(null);
    const projectId = scopedProject ?? chosenProject;

    const apply = useListQuery({ tab, ...filters });

    // Selection is per tab and per list: keeping four ticked rows across a tab
    // change would arm a bulk action against sites that are no longer on screen.
    useEffect(() => setSelected(new Set()), [tab, filters.list]);

    const toggle = useCallback((id: number, next: boolean) => {
        setSelected((current) => {
            const updated = new Set(current);

            if (next) {
                updated.add(id);
            } else {
                updated.delete(id);
            }

            return updated;
        });
    }, []);

    const toggleAll = useCallback(
        (next: boolean) => setSelected(next ? new Set(rows.map((row) => row.id)) : new Set()),
        [rows],
    );

    // A move is reversible, so it says so. Every field undo needs came back
    // with the flash — the note and the reason are gone from the database by
    // the time this runs, and re-reading them would find nothing.
    const moved = page.props.flash.moved;

    useEffect(() => {
        // Null, not absent: the flash bag always carries the key and fills it
        // with null when nothing was moved, so an `=== undefined` guard lets a
        // null through and the toast dereferences it.
        if (!moved) return;

        toast({
            tone: 'success',
            title: `${moved.domain} moved to ${LIST_NAMES[moved.to]}`,
            action: {
                label: 'Undo',
                onSelect: () =>
                    router.post('/lists/move/undo', { ...moved }, { preserveScroll: true, preserveState: false }),
            },
            // Longer than the default five seconds: an undo nobody had time to
            // read is an undo that does not exist.
            duration: 9000,
        });
    }, [moved, toast]);

    const exportHref = `/lists/export?${new URLSearchParams(
        Object.entries({ tab, q: filters.q, category: filters.category, sort: filters.sort, list: filters.list })
            .filter(([, value]) => value !== null && value !== '')
            .map(([key, value]) => [key, String(value)]),
    ).toString()}`;

    const shared = {
        rows,
        ranges,
        filters,
        categories,
        sorts,
        wishlists,
        selected,
        onToggle: toggle,
        onToggleAll: toggleAll,
        apply,
        exportHref,
    };

    return (
        <AppShell title="My lists" crumbs={[{ label: 'My lists' }]}>
            <Head title="My lists" />

            {/* min-w-0 all the way down: these are column flex containers,
                whose items default to min-width:auto — without it a table wider
                than the phone stretches the whole chain and the page scrolls
                sideways instead of the table scrolling inside its own box. */}
            <div className="flex min-w-0 flex-col gap-5">
                <header>
                    <h1 className="font-sora text-xl font-semibold text-ink-900">My lists</h1>
                    <p className="mt-1 text-sm text-ink-500">
                        Sites you have saved, planned or ruled out.
                    </p>
                </header>

                <Tabs
                    items={TAB_LABELS.map((item) => ({
                        id: item.id,
                        label: item.label,
                        count: counts[item.id],
                    }))}
                    value={tab}
                    onChange={(next) =>
                        router.get(
                            '/lists',
                            { tab: next },
                            { preserveScroll: true, preserveState: false },
                        )
                    }
                />

                {tab === 'favorites' && (
                    <FavoritesTab
                        {...shared}
                        projects={projects}
                        projectId={projectId}
                        onProject={setChosenProject}
                    />
                )}

                {tab === 'wishlist' && (
                    <WishlistTab {...shared} summary={summary} projectId={projectId} />
                )}

                {tab === 'blacklist' && (
                    <BlacklistTab
                        {...shared}
                        report={page.props.flash.importReport ?? null}
                    />
                )}
            </div>
        </AppShell>
    );
}

const LIST_NAMES: Record<ListTab, string> = {
    favorites: 'favorites',
    wishlist: 'your wishlist',
    blacklist: 'the blacklist',
};
