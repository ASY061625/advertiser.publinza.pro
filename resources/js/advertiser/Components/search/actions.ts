import { router } from '@inertiajs/react';
import { fuzzyMatches } from '@shared/lib/highlight';
import type { SearchItem } from '@shared/types/search';

export interface PaletteAction {
    id: string;
    title: string;
    /** What it does. Shown under the title so a command is never a guess. */
    subtitle: string;
    icon: string;
    /** Extra words the fuzzy match should consider. */
    keywords: string;
    run: (helpers: ActionHelpers) => void;
}

export interface ActionHelpers {
    /** Opens the add-post wizard, which lives above the page in a provider. */
    addPost: () => void;
}

/**
 * The eight things this app can do, from anywhere.
 *
 * A fixed list rather than a search: eight commands are known at build time, and
 * asking a server whether "Log out" contains the letters somebody typed is a
 * round trip to learn something the client already knows. It is also why this
 * group survives a zero-result search — the palette stays useful when the
 * catalog has nothing to say.
 */
export const ACTIONS: PaletteAction[] = [
    {
        id: 'action-create-project',
        title: 'Create project',
        subtitle: 'Set up a new site to promote',
        icon: 'folder',
        keywords: 'new project add site campaign',
        run: () => router.visit('/projects/create'),
    },
    {
        id: 'action-add-post',
        title: 'Add post',
        subtitle: 'Buy a placement on a website',
        icon: 'plus',
        keywords: 'new placement buy order article guest',
        run: ({ addPost }) => addPost(),
    },
    {
        id: 'action-find-website',
        title: 'Find a website',
        subtitle: 'Browse the catalog',
        icon: 'globe',
        keywords: 'catalog sites search browse publishers',
        run: () => router.visit('/catalog'),
    },
    {
        id: 'action-top-up',
        title: 'Top up balance',
        subtitle: 'Add funds to your account',
        icon: 'wallet',
        keywords: 'money add funds payment deposit credit',
        run: () => router.visit('/balance?tab=top-up'),
    },
    {
        id: 'action-cart',
        title: 'View cart',
        subtitle: 'What you have not checked out yet',
        icon: 'cart',
        keywords: 'basket checkout order',
        run: () => router.visit('/cart'),
    },
    {
        id: 'action-transactions',
        title: 'Open transactions',
        subtitle: 'Every movement on your balance',
        icon: 'list',
        keywords: 'ledger history statement money spend',
        run: () => router.visit('/balance?tab=transactions'),
    },
    {
        id: 'action-settings',
        title: 'Account settings',
        subtitle: 'Profile, company, security and notifications',
        icon: 'lock',
        keywords: 'profile preferences password two factor email',
        run: () => router.visit('/profile'),
    },
    {
        id: 'action-logout',
        title: 'Log out',
        subtitle: 'End this session',
        icon: 'logout',
        keywords: 'sign out exit leave',
        run: () => router.post('/logout'),
    },
];

/**
 * The actions matching a query, as a palette group.
 *
 * An empty query returns nothing rather than all eight: the empty state belongs
 * to recent searches and recently viewed work, and eight commands above them
 * would bury it.
 *
 * `whenEmpty` is passed when the search itself found nothing, and it only
 * widens the list as a *last* resort: the matches still win if there are any.
 * Somebody who typed "top up" and got no websites back wants Top up balance,
 * not that plus seven other commands — but somebody who typed a domain that
 * does not exist wants all eight, because the alternative is an empty box at
 * exactly the moment they need another way through.
 */
export function matchActions(query: string, helpers: ActionHelpers, whenEmpty = false): SearchItem[] {
    const trimmed = query.trim();

    if (trimmed === '' && !whenEmpty) return [];

    const matched = ACTIONS.filter((action) => fuzzyMatches(`${action.title} ${action.keywords}`, trimmed));
    const shown = matched.length > 0 || !whenEmpty ? matched : ACTIONS;

    return shown.map((action) => ({
        id: action.id,
        title: action.title,
        subtitle: action.subtitle,
        icon: action.icon,
        // Actions do something rather than going somewhere; href is the
        // fallback the palette never reaches for them.
        href: '#',
        run: () => action.run(helpers),
    }));
}
