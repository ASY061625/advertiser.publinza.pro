import { router } from '@inertiajs/react';
import { cn } from '@shared/lib/cn';
import { date, money } from '@shared/lib/format';
import { Badge, ChatIcon, Dropdown, GlobeIcon, IconButton, MoreIcon, useToast, type StatusKey } from '@shared/ui';
import type { RecentPost } from '@shared/types/dashboard';

interface Props {
    posts: RecentPost[];
}

const ANCHOR_MAX = 28;

/**
 * The eight most recent posts.
 *
 * Anchor text is truncated in JavaScript rather than by CSS ellipsis, because
 * the row also needs the untruncated string in a `title` — and a CSS-clipped
 * cell gives you no way to know whether clipping happened.
 */
export function RecentPosts({ posts }: Props) {
    const { toast } = useToast();

    function actions(post: RecentPost) {
        const items = [
            {
                id: 'view',
                label: 'View post',
                onSelect: () => router.visit(`/posts/${post.id}`),
            },
            {
                id: 'conversation',
                label: 'Open conversation',
                icon: <ChatIcon size={14} />,
                onSelect: () => router.visit(`/conversations?post=${post.id}`),
            },
            {
                id: 'copy',
                label: 'Copy published URL',
                disabled: post.publishedUrl === null,
                onSelect: () => {
                    if (!post.publishedUrl) return;

                    void navigator.clipboard
                        .writeText(post.publishedUrl)
                        .then(() => toast({ tone: 'success', title: 'Published URL copied.' }))
                        .catch(() =>
                            toast({
                                tone: 'danger',
                                title: 'Could not copy.',
                                description: post.publishedUrl ?? undefined,
                            }),
                        );
                },
            },
        ];

        return items;
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] border-collapse text-left">
                <caption className="sr-only">Your eight most recent posts</caption>
                <thead>
                    <tr className="border-b border-subtle">
                        {['Website', 'Project', 'Anchor', 'Status', 'Price', 'Created', ''].map((heading, i) => (
                            <th
                                key={heading || i}
                                scope="col"
                                className={cn(
                                    'px-3 py-2 text-xs font-medium uppercase tracking-wide text-ink-500',
                                    (heading === 'Price' || heading === '') && 'text-right',
                                )}
                            >
                                {heading || <span className="sr-only">Actions</span>}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {posts.map((post) => {
                        const anchor = post.anchorText ?? '';
                        const clipped = anchor.length > ANCHOR_MAX;

                        return (
                            <tr
                                key={post.id}
                                className="border-b border-subtle transition-colors duration-fast ease-standard last:border-0 hover:bg-row-hover"
                            >
                                <td className="px-3 py-3">
                                    <span className="flex items-center gap-2">
                                        {post.favicon ? (
                                            <img
                                                src={post.favicon}
                                                alt=""
                                                width={16}
                                                height={16}
                                                loading="lazy"
                                                className="size-4 shrink-0 rounded-[3px]"
                                            />
                                        ) : (
                                            <GlobeIcon size={16} className="shrink-0 text-ink-300" />
                                        )}
                                        <span className="text-sm font-medium text-ink-900">{post.domain}</span>
                                    </span>
                                </td>
                                <td className="px-3 py-3 text-sm text-ink-700">{post.project ?? '—'}</td>
                                <td className="px-3 py-3 text-sm text-ink-700">
                                    {anchor === '' ? (
                                        <span className="text-ink-300">—</span>
                                    ) : (
                                        <span title={clipped ? anchor : undefined}>
                                            {clipped ? `${anchor.slice(0, ANCHOR_MAX)}…` : anchor}
                                        </span>
                                    )}
                                </td>
                                <td className="px-3 py-3">
                                    <Badge status={post.badge as StatusKey} label={post.statusLabel} />
                                </td>
                                <td className="num px-3 py-3 text-right text-sm text-ink-900">
                                    {money(post.priceCents)}
                                </td>
                                <td className="num px-3 py-3 text-sm text-ink-500">
                                    {post.createdAt ? date(post.createdAt) : '—'}
                                </td>
                                <td className="px-3 py-3 text-right">
                                    <Dropdown
                                        items={actions(post)}
                                        trigger={
                                            <IconButton
                                                label={`Actions for ${post.domain}`}
                                                variant="ghost"
                                                size="sm"
                                                icon={<MoreIcon size={16} />}
                                            />
                                        }
                                    />
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
