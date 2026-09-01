import { Link } from '@inertiajs/react';
import { Button, FolderIcon, ListIcon, SearchIcon } from '@shared/ui';

/**
 * Three empty states, and they are three different messages.
 *
 * A brand-new account, an account with projects but nothing bought, and an
 * account with plenty of history that happens to have picked a quiet fortnight
 * are not the same situation. Showing one generic "nothing here" to all three
 * would tell the first person nothing about what to do next and would make the
 * third think their data had been lost.
 */

/** No projects at all. The whole dashboard body is replaced by this. */
export function NoProjectsState() {
    return (
        <section className="flex flex-col items-center justify-center rounded-card border border-subtle bg-card px-6 py-20 text-center shadow-card">
            <span className="flex size-14 items-center justify-center rounded-card bg-brand-subtle text-brand">
                <FolderIcon size={26} />
            </span>

            <h2 className="mt-5 font-sora text-xl font-semibold text-ink-900">Start with a project</h2>

            <p className="mt-2 max-w-lg text-md text-ink-700">
                A project is one site you are building links for. Create one, and the catalog, your cart and every
                report on this page organise themselves around it.
            </p>

            <Link href="/projects/create" className="mt-6 inline-block">
                <Button size="lg">Create your first project</Button>
            </Link>

            <p className="mt-4 text-sm text-ink-500">
                Takes about a minute. You can browse the{' '}
                <Link href="/catalog" className="font-medium text-brand underline underline-offset-2">
                    catalog
                </Link>{' '}
                first if you would rather look around.
            </p>
        </section>
    );
}

/** Projects exist, but nothing has ever been ordered. Row 1 stays, showing zeros. */
export function NoPostsState() {
    return (
        <section className="flex flex-col items-center justify-center rounded-card border border-subtle bg-card px-6 py-16 text-center shadow-card">
            <span className="flex size-14 items-center justify-center rounded-card bg-brand-subtle text-brand">
                <ListIcon size={26} />
            </span>

            <h2 className="mt-5 font-sora text-lg font-semibold text-ink-900">
                Your project is ready. Now pick where it gets published.
            </h2>

            <p className="mt-2 max-w-lg text-md text-ink-700">
                Every site in the catalog is one we own and run ourselves, so a placement you order is a placement that
                goes live. Filter by traffic, topic and price, then add what fits.
            </p>

            <Link href="/catalog" className="mt-6 inline-block">
                <Button size="lg">Browse the catalog</Button>
            </Link>
        </section>
    );
}

/**
 * There is history, just none inside the chosen range. Deliberately quieter
 * than the other two — this is a filter result, not a milestone.
 */
export function EmptyRangeState({ onReset }: { onReset: () => void }) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 rounded-card border border-dashed border-subtle bg-sunken px-6 py-12 text-center">
            <span className="text-ink-300">
                <SearchIcon size={22} />
            </span>

            <p className="text-md font-medium text-ink-700">No activity in this range</p>

            <p className="max-w-sm text-sm text-ink-500">
                Your account has posts outside these dates. Widen the range to see them.
            </p>

            <Button variant="secondary" size="sm" onClick={onReset} className="mt-1">
                Reset to last 30 days
            </Button>
        </div>
    );
}
