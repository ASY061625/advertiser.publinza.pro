import { Head, Link } from '@inertiajs/react';
import { AppShell } from '../../Layouts/AppShell';
import { Button, DownloadIcon, EmptyState, PlusIcon } from '@shared/ui';
import { number } from '@shared/lib/format';
import type { Paginated } from '@shared/types';
import type { ColumnPreferences, PostFilterState, PostOptions, PostRow, SavedViewRecord } from '@shared/types/posts';
import { PostsGrid, postsExportHref } from '../../Components/posts/PostsGrid';

interface Props {
    posts: Paginated<PostRow>;
    tabCounts: Record<string, number>;
    filters: PostFilterState;
    hasAnyPosts: boolean;
    isFiltering: boolean;
    options: PostOptions;
    columns: ColumnPreferences;
    savedViews: SavedViewRecord[];
}

/**
 * Every post across every project.
 *
 * The grid itself is PostsGrid, which a project's Post management tab renders
 * too — this page is the page around it: the title, the export, and the empty
 * state for an account that has never bought a placement.
 */
export default function PostsIndex({
    posts,
    tabCounts,
    filters,
    hasAnyPosts,
    isFiltering,
    options,
    columns,
    savedViews,
}: Props) {
    const empty = !hasAnyPosts && !isFiltering;

    return (
        <AppShell title="Posts">
            <Head title="Posts" />

            <header className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex items-baseline gap-3">
                    <h1 className="font-sora text-xl font-semibold text-ink-900">Posts</h1>
                    <span className="num text-sm text-ink-500">
                        {number(posts.total)} {posts.total === 1 ? 'post' : 'posts'}
                    </span>
                </div>

                <div className="flex items-center gap-2">
                    <Button
                        variant="secondary"
                        disabled={empty}
                        onClick={() => {
                            window.location.href = postsExportHref(filters);
                        }}
                    >
                        <DownloadIcon size={14} />
                        Export CSV
                    </Button>
                    <Link href="/posts/create">
                        <Button>
                            <PlusIcon size={14} />
                            Add post
                        </Button>
                    </Link>
                </div>
            </header>

            <div className="mt-5">
                <PostsGrid
                    posts={posts}
                    tabCounts={tabCounts}
                    filters={filters}
                    isFiltering={isFiltering}
                    options={options}
                    columns={columns}
                    savedViews={savedViews}
                    path="/posts"
                    emptyState={
                        <EmptyState
                            illustration={<PlusIcon size={26} />}
                            direction="Time to add your first post!"
                            action={
                                <span className="flex flex-col items-center gap-3">
                                    <Link href="/posts/create">
                                        <Button size="lg">Add post</Button>
                                    </Link>
                                    <Link
                                        href="/catalog"
                                        className="text-sm font-medium text-brand underline underline-offset-2"
                                    >
                                        Find a website
                                    </Link>
                                </span>
                            }
                        />
                    }
                />
            </div>
        </AppShell>
    );
}
