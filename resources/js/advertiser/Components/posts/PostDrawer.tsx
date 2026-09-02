import { useEffect, useState } from 'react';
import { cn } from '@shared/lib/cn';
import { date, money, number } from '@shared/lib/format';
import { Badge, Button, Drawer, Skeleton, SkeletonText } from '@shared/ui';
import type { PostDetail } from '@shared/types/posts';

interface Props {
    postId: number | null;
    onClose: () => void;
    onCancelPost: (id: number) => void;
}

type TabId = 'details' | 'article' | 'messages' | 'history';

const TABS: { id: TabId; label: string }[] = [
    { id: 'details', label: 'Details' },
    { id: 'article', label: 'Article' },
    { id: 'messages', label: 'Messages' },
    { id: 'history', label: 'History' },
];

/**
 * The row drawer: everything about one post without leaving the grid.
 *
 * Opening a row must not lose the reader's place in a filtered, sorted,
 * scrolled list of a hundred rows — going to a page and coming back would.
 * The drawer is deep-linkable at /posts?post={id}, so a row is still something
 * that can be sent to someone.
 */
export function PostDrawer({ postId, onClose, onCancelPost }: Props) {
    const [tab, setTab] = useState<TabId>('details');
    const [detail, setDetail] = useState<PostDetail | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        if (postId === null) return;

        // Clear first: showing the previous post's article under a new post's
        // header for a beat is worse than showing a skeleton.
        setDetail(null);
        setFailed(false);
        setTab('details');

        let current = true;

        void fetch(`/posts/${postId}/detail`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!response.ok) throw new Error(String(response.status));

                return response.json() as Promise<PostDetail>;
            })
            .then((payload) => current && setDetail(payload))
            .catch(() => current && setFailed(true));

        return () => {
            current = false;
        };
    }, [postId]);

    return (
        <Drawer
            open={postId !== null}
            onClose={onClose}
            title={detail ? `Post #${detail.id}` : postId === null ? '' : `Post #${postId}`}
            className="max-w-[560px]"
            footer={
                detail?.canCancel ? (
                    <Button variant="danger" onClick={() => onCancelPost(detail.id)}>
                        Cancel post
                    </Button>
                ) : undefined
            }
        >
            {failed ? (
                <p className="py-8 text-center text-sm text-ink-500">
                    We could not load this post. Close the drawer and try again.
                </p>
            ) : detail === null ? (
                <div className="flex flex-col gap-4 py-2">
                    <Skeleton width="w-32" height="h-6" />
                    <SkeletonText lines={6} />
                </div>
            ) : (
                <>
                    <div className="mb-4 flex items-center gap-3">
                        <Badge status={detail.badge} label={detail.statusLabel} />
                        <span className="num text-sm text-ink-500">{money(detail.details.priceCents)}</span>
                    </div>

                    <div role="tablist" className="flex gap-1 border-b border-subtle">
                        {TABS.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                role="tab"
                                aria-selected={tab === item.id}
                                onClick={() => setTab(item.id)}
                                className={cn(
                                    'border-b-2 px-3 pb-2 pt-1 text-base font-medium transition-colors duration-fast',
                                    tab === item.id
                                        ? 'border-brand text-brand'
                                        : 'border-transparent text-ink-500 hover:text-ink-700',
                                )}
                            >
                                {item.label}
                                {item.id === 'messages' && detail.messages.length > 0 && (
                                    <span className="num ml-1.5 text-xs text-ink-500">{detail.messages.length}</span>
                                )}
                            </button>
                        ))}
                    </div>

                    <div className="pt-4">
                        {tab === 'details' && <Details detail={detail} />}
                        {tab === 'article' && <Article detail={detail} />}
                        {tab === 'messages' && <Messages detail={detail} />}
                        {tab === 'history' && <History detail={detail} />}
                    </div>
                </>
            )}
        </Drawer>
    );
}

function Details({ detail }: { detail: PostDetail }) {
    const d = detail.details;

    const rows: [string, React.ReactNode][] = [
        ['Website', d.domain ?? '—'],
        [
            'Metrics',
            d.dr === null && d.traffic === null
                ? '—'
                : `DR ${d.dr ?? '—'} · ${d.traffic === null ? '—' : `${number(d.traffic)}/mo`}`,
        ],
        ['Country', d.country ?? '—'],
        ['Project', d.project ?? '—'],
        ['Folder', d.folder ?? '—'],
        ['Anchor text', d.anchorText ?? '—'],
        [
            'Target URL',
            d.targetUrl ? (
                <a
                    href={d.targetUrl}
                    target="_blank"
                    rel="noopener noreferrer nofollow"
                    className="break-all text-brand hover:underline"
                >
                    {d.targetUrl}
                </a>
            ) : (
                '—'
            ),
        ],
        ['Content mode', d.contentMode],
        ['Price', money(d.priceCents)],
        ['Created', d.createdAt ? date(d.createdAt) : '—'],
        ['Published', d.publishedAt ? date(d.publishedAt) : '—'],
        ['Deadline', d.deadlineAt ? date(d.deadlineAt) : '—'],
        [
            'Published URL',
            d.publishedUrl ? (
                <a
                    href={d.publishedUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="break-all text-brand hover:underline"
                >
                    {d.publishedUrl}
                </a>
            ) : (
                '—'
            ),
        ],
    ];

    return (
        <>
            {d.rejectionReason && (
                <p className="mb-4 rounded-card bg-danger-bg px-3 py-2 text-sm text-danger">
                    Rejected: {d.rejectionReason}
                </p>
            )}

            <dl className="grid grid-cols-[minmax(0,7rem)_1fr] gap-x-4 gap-y-2.5 text-sm">
                {rows.map(([label, value]) => (
                    <div key={label} className="contents">
                        <dt className="text-ink-500">{label}</dt>
                        <dd className="min-w-0 text-ink-900">{value}</dd>
                    </div>
                ))}
            </dl>
        </>
    );
}

function Article({ detail }: { detail: PostDetail }) {
    const article = detail.article;

    if (article === null) {
        return (
            <p className="py-8 text-center text-sm text-ink-500">
                No article yet. It appears here once a draft is submitted.
            </p>
        );
    }

    return (
        <div>
            <h4 className="font-sora text-md font-semibold text-ink-900">{article.title}</h4>
            <p className="num mt-1 text-sm text-ink-500">
                {number(article.wordCount)} words · version {article.version} of {article.versions}
                {article.approvedAt && ` · approved ${date(article.approvedAt)}`}
            </p>

            {article.bodyHtml ? (
                // Publisher-authored HTML. Reduced to an allowlist of
                // formatting tags by App\Support\HtmlSanitizer before it is
                // sent — see GetPostDetail. Nothing here is trusted markup.
                <div
                    className="prose-publinza mt-4 max-w-none text-sm"
                    dangerouslySetInnerHTML={{ __html: article.bodyHtml }}
                />
            ) : (
                <p className="mt-4 text-sm text-ink-500">
                    {article.hasFile
                        ? 'This draft was uploaded as a file. Use Download article to read it.'
                        : 'This draft has no body yet.'}
                </p>
            )}
        </div>
    );
}

function Messages({ detail }: { detail: PostDetail }) {
    if (detail.messages.length === 0) {
        return <p className="py-8 text-center text-sm text-ink-500">No messages about this post yet.</p>;
    }

    return (
        <ul className="flex flex-col gap-3">
            {detail.messages.map((message) => {
                // The advertiser is 'user'; 'admin' and 'system' are Publinza.
                const mine = message.senderType === 'user';

                return (
                    <li
                        key={message.id}
                        className={cn(
                            'rounded-card px-3 py-2.5 text-sm',
                            mine ? 'ml-8 bg-brand-subtle' : 'mr-8 bg-sunken',
                        )}
                    >
                        <p className="mb-1 flex items-center gap-2 text-xs text-ink-500">
                            <span className="font-medium">{mine ? 'You' : 'Publinza'}</span>
                            {message.createdAt && <span className="num">{date(message.createdAt)}</span>}
                            {!mine && message.readAt === null && (
                                <span className="rounded-pill bg-brand px-1.5 py-0.5 text-white">New</span>
                            )}
                        </p>
                        <p className="whitespace-pre-wrap text-ink-900">{message.body}</p>

                        {message.attachments.length > 0 && (
                            <ul className="mt-2 flex flex-wrap gap-2">
                                {message.attachments.map((file) => (
                                    <li key={file.id} className="rounded-pill bg-card px-2 py-0.5 text-xs text-ink-700">
                                        {file.name}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

function History({ detail }: { detail: PostDetail }) {
    if (detail.history.length === 0) {
        return <p className="py-8 text-center text-sm text-ink-500">No history recorded yet.</p>;
    }

    return (
        <ol className="flex flex-col">
            {detail.history.map((entry, index) => (
                <li key={entry.id} className="flex gap-3">
                    <span className="flex flex-col items-center">
                        <span className="mt-1.5 size-2 shrink-0 rounded-pill bg-brand" />
                        {index < detail.history.length - 1 && <span className="w-px flex-1 bg-ink-300" />}
                    </span>

                    <span className="pb-4 text-sm">
                        <span className="block text-ink-900">
                            {entry.from ? `${label(entry.from)} → ${label(entry.to)}` : `Created as ${label(entry.to)}`}
                        </span>
                        <span className="num block text-xs text-ink-500">
                            {entry.createdAt ? date(entry.createdAt) : ''} · {entry.actorType}
                        </span>
                        {entry.note && <span className="mt-1 block text-ink-700">{entry.note}</span>}
                    </span>
                </li>
            ))}
        </ol>
    );
}

function label(status: string): string {
    return status.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());
}
