import { router } from '@inertiajs/react';
import { Dropdown, HeartIcon, IconButton, ListIcon, MoreIcon, TrashIcon } from '@shared/ui';
import type { ListRow, ListTab, WishlistSummary } from '@shared/types/lists';

interface Props {
    row: ListRow;
    from: ListTab;
    wishlists: WishlistSummary[];
    /** Extra items this tab wants above the move ones. */
    extra?: { id: string; label: string; destructive?: boolean; onSelect: () => void }[];
}

const LABELS: Record<ListTab, string> = {
    favorites: 'favorites',
    wishlist: 'a wishlist',
    blacklist: 'the blacklist',
};

/**
 * Moving a site to another list, from any row on any tab.
 *
 * One menu shared by all three because the move is the same operation whichever
 * direction it runs — the server takes a from and a to. Three bespoke menus
 * would have been three places to forget a direction.
 */
export function RowMenu({ row, from, wishlists, extra = [] }: Props) {
    const targets = (['favorites', 'wishlist', 'blacklist'] as ListTab[]).filter((tab) => tab !== from);

    return (
        <Dropdown
            trigger={
                <IconButton
                    label={`More actions for ${row.domain}`}
                    variant="ghost"
                    size="sm"
                    icon={<MoreIcon size={16} />}
                />
            }
            items={[
                ...extra,
                ...targets.flatMap((to) => {
                    // A wishlist move names the list, because "move to a
                    // wishlist" is ambiguous the moment somebody has two.
                    if (to === 'wishlist' && wishlists.length > 1) {
                        return wishlists.map((list) => ({
                            id: `move-${to}-${list.id}`,
                            label: `Move to ${list.name}`,
                            icon: <ListIcon size={14} />,
                            onSelect: () => move(row, from, to, list.id),
                        }));
                    }

                    return [
                        {
                            id: `move-${to}`,
                            label: `Move to ${LABELS[to]}`,
                            icon: to === 'favorites' ? <HeartIcon size={14} /> : <ListIcon size={14} />,
                            destructive: to === 'blacklist',
                            onSelect: () => move(row, from, to, null),
                        },
                    ];
                }),
            ]}
        />
    );
}

export function move(row: ListRow, from: ListTab, to: ListTab, wishlistId: number | null) {
    router.post(
        '/lists/move',
        { website_id: row.id, from, to, wishlist_id: wishlistId },
        { preserveScroll: true, preserveState: false },
    );
}

/** The remove control every tab puts beside its menu. */
export function RemoveButton({ label, onClick }: { label: string; onClick: () => void }) {
    return <IconButton label={label} variant="ghost" size="sm" icon={<TrashIcon size={16} />} onClick={onClick} />;
}
