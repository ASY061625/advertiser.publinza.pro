import {
    Badge,
    CartIcon,
    FolderIcon,
    GlobeIcon,
    ListIcon,
    LockIcon,
    LogOutIcon,
    PlusIcon,
    WalletIcon,
} from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { money, number } from '@shared/lib/format';
import { segments } from '@shared/lib/highlight';
import type { StatusKey } from '@shared/ui';
import type { SearchGroupKey, SearchItem } from '@shared/types/search';

const ACTION_ICONS: Record<string, (p: { size?: number }) => JSX.Element> = {
    folder: FolderIcon,
    plus: PlusIcon,
    globe: GlobeIcon,
    wallet: WalletIcon,
    cart: CartIcon,
    list: ListIcon,
    lock: LockIcon,
    logout: LogOutIcon,
};

/**
 * The matched run, marked.
 *
 * A `<mark>` rather than a span, because that is what the element is for and it
 * carries the meaning to a screen reader without an aria attribute. The default
 * yellow is overridden — a highlighter over half a domain is not the tone of
 * the rest of the app.
 */
function Highlighted({ text, query }: { text: string; query: string }) {
    return (
        <>
            {segments(text, query).map((segment, index) =>
                segment.match ? (
                    <mark key={index} className="bg-transparent font-semibold text-brand">
                        {segment.text}
                    </mark>
                ) : (
                    <span key={index}>{segment.text}</span>
                ),
            )}
        </>
    );
}

/**
 * The 20px mark beside a domain.
 *
 * A monogram, not a favicon fetched from a third party — the same call the
 * catalog and the dashboard make, and more strongly here: a palette that asked
 * an external service for an icon per keystroke would stream what somebody is
 * searching for to somebody else, one letter at a time.
 */
function DomainMark({ domain }: { domain: string }) {
    return (
        <span
            aria-hidden="true"
            className="flex size-5 shrink-0 items-center justify-center rounded-[4px] bg-sunken text-xs font-semibold text-ink-700"
        >
            {(domain.replace(/^www\./, '')[0] ?? '?').toUpperCase()}
        </span>
    );
}

interface Props {
    item: SearchItem;
    groupKey: SearchGroupKey;
    query: string;
    active: boolean;
    onHover: () => void;
    onChoose: () => void;
}

export function SearchRow({ item, groupKey, query, active, onHover, onChoose }: Props) {
    return (
        <button
            type="button"
            role="option"
            id={`palette-${item.id}`}
            aria-selected={active}
            // Pointer, not mouse: the same handler serves a trackpad, a pen and
            // a mouse, and a touch drag over the list should not select rows.
            onPointerMove={onHover}
            onClick={onChoose}
            className={cn(
                'flex w-full items-center gap-3 px-4 py-2.5 text-left',
                active ? 'bg-brand-subtle' : 'bg-card',
            )}
        >
            <Leading item={item} groupKey={groupKey} />

            <span className="flex min-w-0 flex-1 flex-col">
                <span className="truncate text-base text-ink-900">
                    <Highlighted text={item.title} query={query} />
                </span>

                <Subtitle item={item} groupKey={groupKey} query={query} />
            </span>

            <Trailing item={item} groupKey={groupKey} />
        </button>
    );
}

function Leading({ item, groupKey }: { item: SearchItem; groupKey: SearchGroupKey }) {
    if (groupKey === 'projects') {
        return (
            <span
                aria-hidden="true"
                className="size-2.5 shrink-0 rounded-pill"
                style={{ backgroundColor: item.color ?? 'var(--ink-300)' }}
            />
        );
    }

    if (groupKey === 'actions') {
        const Icon = ACTION_ICONS[item.icon ?? ''] ?? ListIcon;

        return (
            <span
                aria-hidden="true"
                className="flex size-5 shrink-0 items-center justify-center text-ink-500"
            >
                <Icon size={15} />
            </span>
        );
    }

    return <DomainMark domain={item.title} />;
}

function Subtitle({
    item,
    groupKey,
    query,
}: {
    item: SearchItem;
    groupKey: SearchGroupKey;
    query: string;
}) {
    if (groupKey === 'conversations') {
        return (
            <span className="truncate text-sm text-ink-500">
                <Highlighted text={item.subtitle ?? ''} query={query} />
                {item.excerpt !== null && item.excerpt !== undefined && (
                    <span className="text-ink-300"> · {item.excerpt}</span>
                )}
            </span>
        );
    }

    if (item.subtitle === null || item.subtitle === '') return null;

    return (
        <span className="truncate text-sm text-ink-500">
            <Highlighted text={item.subtitle} query={query} />
        </span>
    );
}

function Trailing({ item, groupKey }: { item: SearchItem; groupKey: SearchGroupKey }) {
    if (groupKey === 'websites' && typeof item.meta === 'number') {
        return <span className="num shrink-0 text-sm text-ink-700">{money(item.meta)}</span>;
    }

    if (groupKey === 'projects' && typeof item.meta === 'number') {
        return (
            <span className="num shrink-0 text-sm text-ink-500">
                {number(item.meta)} {item.meta === 1 ? 'post' : 'posts'}
            </span>
        );
    }

    if (groupKey === 'posts' && item.status !== undefined) {
        return (
            <span className="shrink-0">
                {/* The server sends PostStatus::badgeKey(), which is already
                    one of the eight, plus the lifecycle's own longer label. */}
                <Badge status={item.status as StatusKey} label={item.statusLabel ?? item.status} />
            </span>
        );
    }

    return null;
}
