import { Link } from '@inertiajs/react';
import { Button } from '@shared/ui';

/**
 * The card a project shows before it has any posts.
 *
 * It sits above Deals rather than replacing it: the panels below are still
 * correct — four zeros and no money — and hiding them would mean the page
 * rearranges itself the moment the first post lands. This card is the one thing
 * to do next, and it leaves when there is something else to look at.
 */
export function FirstDealCard({ projectId }: { projectId: number }) {
    return (
        <section className="rounded-card border border-brand bg-brand-subtle p-5">
            <h2 className="font-sora text-md font-semibold text-ink-900">Make your first deal</h2>

            <p className="mt-1 max-w-prose text-sm text-ink-700">
                Pick a site in your niche and place your first article.
            </p>

            <div className="mt-4 flex flex-wrap gap-2">
                <Link href={`/catalog?project=${projectId}`}>
                    <Button>Find a website</Button>
                </Link>

                <Link href={`/catalog?project=${projectId}&intent=add-post`}>
                    <Button variant="secondary">Add post</Button>
                </Link>
            </div>
        </section>
    );
}
