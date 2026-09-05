import { Button, SparkleIcon } from '@shared/ui';
import type { PostDraftCard } from '@shared/types/postWizard';
import { useAddPost } from './AddPostProvider';

const STEPS = ['Project and folder', 'Choosing the website', 'Content', 'Review'];

/**
 * "Continue where you left off."
 *
 * On the dashboard because that is where people come back to. A draft nobody
 * is told about is a draft nobody resumes, and the wizard is exactly the kind
 * of flow a phone call interrupts halfway through.
 *
 * It names the site and the project rather than saying "an unfinished post",
 * because the first thing anybody wants to know is whether it is worth the
 * click.
 */
export function ResumeDraftCard({ draft }: { draft: PostDraftCard }) {
    const addPost = useAddPost();

    return (
        <section className="flex flex-wrap items-center gap-4 rounded-card border border-brand bg-brand-subtle px-5 py-4">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-card text-brand">
                <SparkleIcon size={18} />
            </span>

            <div className="min-w-0 flex-1">
                <p className="font-sora text-base font-semibold text-ink-900">Continue where you left off</p>
                <p className="text-sm text-ink-500">
                    {draft.summary}
                    {' — '}
                    stopped at <span className="text-ink-700">{STEPS[draft.step - 1] ?? 'the start'}</span>
                    {draft.savedAt !== null && <>, saved {relative(draft.savedAt)}</>}.
                </p>
            </div>

            <Button onClick={() => addPost.open({ resume: true })}>Continue</Button>
        </section>
    );
}

/** "3 minutes ago" — close enough, and no library for one string. */
function relative(iso: string): string {
    const minutes = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60_000));

    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'} ago`;

    const hours = Math.round(minutes / 60);

    if (hours < 24) return `${hours} ${hours === 1 ? 'hour' : 'hours'} ago`;

    const days = Math.round(hours / 24);

    return `${days} ${days === 1 ? 'day' : 'days'} ago`;
}
