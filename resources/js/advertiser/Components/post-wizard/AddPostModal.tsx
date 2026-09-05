import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    Button,
    CartIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    Modal,
    Skeleton,
    Spinner,
    useToast,
} from '@shared/ui';
import type { PostWizardState, WizardOptions, WizardWebsite } from '@shared/types/postWizard';
import { StepContent } from './StepContent';
import { StepProject, domainMismatch } from './StepProject';
import { StepReview } from './StepReview';
import { StepWebsite } from './StepWebsite';
import { WizardSteps } from './WizardSteps';
import { blankState, fromDraft, usePostWizard } from './usePostWizard';

interface Props {
    open: boolean;
    onClose: () => void;
    /** Set when launched from inside a project; step 1 shows it as a pill. */
    projectId: number | null;
    /** Resume a saved draft rather than starting blank. */
    resume: boolean;
}

const TITLES = ['Project and folder', 'Choose the website', 'Content', 'Review and confirm'];

/**
 * Add post: four steps in a modal, over whatever the advertiser was doing.
 *
 * A modal rather than a page because this is a side errand. Somebody looking at
 * their posts who wants one more should end up back at their posts, not on a
 * route they now have to navigate out of — and the flow is short enough that
 * losing the page behind it would cost more than it gives.
 *
 * It ends at a cart line either way. "Add to cart" leaves it there with
 * everything else; "Place order now" buys that one line and leaves the rest of
 * the cart alone. Same purchase as the catalog's, entered from the other side.
 */
export function AddPostModal({ open, onClose, projectId, resume }: Props) {
    const [options, setOptions] = useState<WizardOptions | null>(null);
    const [site, setSite] = useState<WizardWebsite | null>(null);
    const [article, setArticle] = useState<File | null>(null);
    const [image, setImage] = useState<File | null>(null);
    const [submitting, setSubmitting] = useState<'cart' | 'order' | null>(null);
    const [confirmClose, setConfirmClose] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const { toast } = useToast();

    // The draft card's two labels. Read through a ref because the hook needs
    // this callback to build its payload and the values it reads are produced
    // by the same render — it is only ever called on a later tick.
    const labels = useRef<{ projectName: string | null; websiteDomain: string | null }>({
        projectName: null,
        websiteDomain: null,
    });

    const describe = useCallback(() => labels.current, []);
    const { state, patch, step, goTo, savedAt, save, markSpent, isDirty } = usePostWizard(
        blankState(projectId),
        1,
        { describe },
    );

    // Options and any draft arrive together when the modal opens, so the first
    // step paints once rather than reshuffling as three requests land.
    useEffect(() => {
        if (!open || options !== null) return;

        void fetch('/posts/wizard/options', { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : null))
            .then((body: WizardOptions | null) => {
                if (body === null) return;

                setOptions(body);

                if (resume && body.draft !== null) {
                    patch(fromDraft(body.draft.payload, projectId));
                    goTo(Math.min(4, Math.max(1, body.draft.step)));
                }
            })
            .catch(() => undefined);

        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    // The chosen site's own terms, fetched once per selection. The picker row
    // carries a price; the summary strip and step 3 need the link terms and the
    // word minimum, which a list row has no room for.
    useEffect(() => {
        if (state.websiteSlug === '') {
            setSite(null);

            return;
        }

        let live = true;

        void fetch(`/posts/wizard/websites/${state.websiteSlug}`, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : null))
            .then((body: WizardWebsite | null) => {
                if (live) setSite(body);
            })
            .catch(() => undefined);

        return () => {
            live = false;
        };
    }, [state.websiteSlug]);

    const project = useMemo(
        () => options?.projects.find((entry) => String(entry.id) === state.projectId) ?? null,
        [options, state.projectId],
    );

    labels.current = { projectName: project?.name ?? null, websiteDomain: site?.domain ?? null };

    const folder = project?.folders.find((entry) => String(entry.id) === state.folderId) ?? null;
    // A folder's brief overrides its project's — the rule the folder editor
    // already applies, applied here so the prefill matches what people expect.
    const publisherTask = folder?.publisherTask ?? project?.publisherTask ?? null;

    const blocked = blockerFor(step, state, project?.websiteUrl ?? null);

    function submit(intent: 'cart' | 'order') {
        setSubmitting(intent);
        setErrors({});

        const payload = new FormData();

        payload.append('intent', intent);
        payload.append('project_id', state.projectId);
        payload.append('website_id', state.websiteId);
        payload.append('service_type', state.serviceType);
        payload.append('content_mode', state.contentMode);
        payload.append('express', state.express ? '1' : '0');

        if (state.folderId !== '') payload.append('folder_id', state.folderId);
        if (state.landingPageId !== '' && state.landingPageId !== 'manual') {
            payload.append('landing_page_id', state.landingPageId);
        } else {
            payload.append('anchor_text', state.anchorText);
            payload.append('target_url', state.targetUrl);
        }

        if (state.contentMode === 'publisher_writes') {
            payload.append('brief', state.brief);
            payload.append('keywords', state.keywords);
            payload.append('tone', state.tone);
            if (state.targetWords !== '') payload.append('target_words', state.targetWords);
        } else {
            payload.append('title', state.title);
            payload.append('body', state.body);
            if (article !== null) payload.append('article', article);
            if (image !== null) payload.append('image', image);
        }

        router.post('/posts/wizard', payload, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                // The answers are a cart line now, not a draft.
                markSpent();

                if (intent === 'cart') {
                    // Returns them to where they were, with the one action they
                    // might want next. A redirect to the cart would take away
                    // the page they were working on to show them a page they
                    // did not ask for.
                    toast({
                        tone: 'success',
                        title: `${site?.domain ?? 'Site'} added to your cart`,
                        description: 'It waits there until you check out.',
                        action: { label: 'Open cart', href: '/cart' },
                    });
                }

                close(true);
            },
            onError: setErrors,
            onFinish: () => setSubmitting(null),
        });
    }

    const close = useCallback(
        (force: boolean) => {
            if (!force && isDirty()) {
                setConfirmClose(true);

                return;
            }

            setConfirmClose(false);
            onClose();
        },
        [isDirty, onClose],
    );

    if (!open) return null;

    return (
        <>
            <Modal
                open={!confirmClose}
                onClose={() => close(false)}
                size="xl"
                title={`Add post — ${TITLES[step - 1]}`}
                description="The same purchase as the catalog, from the other end. It ends in your cart either way."
                footer={
                    <div className="flex w-full flex-wrap items-center gap-3">
                        {step > 1 && (
                            <Button variant="secondary" onClick={() => goTo(step - 1)}>
                                <ChevronLeftIcon size={14} />
                                Back
                            </Button>
                        )}

                        <span className="num ml-auto flex items-center gap-2 text-xs text-ink-500">
                            {savedAt !== null && <>Draft saved</>}
                        </span>

                        {step < 4 ? (
                            <Button
                                onClick={() => goTo(step + 1)}
                                disabled={blocked !== null}
                                title={blocked ?? undefined}
                            >
                                Continue
                                <ChevronRightIcon size={14} />
                            </Button>
                        ) : (
                            <>
                                <Button
                                    variant="secondary"
                                    loading={submitting === 'cart'}
                                    disabled={submitting !== null}
                                    onClick={() => submit('cart')}
                                >
                                    <CartIcon size={14} />
                                    Add to cart
                                </Button>

                                <Button
                                    loading={submitting === 'order'}
                                    disabled={submitting !== null}
                                    onClick={() => submit('order')}
                                >
                                    Place order now
                                </Button>
                            </>
                        )}
                    </div>
                }
            >
                <div className="flex flex-col gap-5">
                    <WizardSteps step={step} furthest={furthestFor(state)} onJump={goTo} />

                    {Object.values(errors).length > 0 && (
                        <p role="alert" className="rounded-card bg-danger-bg px-3 py-2 text-sm text-danger">
                            {Object.values(errors)[0]}
                        </p>
                    )}

                    {options === null ? (
                        <div className="flex flex-col gap-3">
                            {Array.from({ length: 4 }, (_, row) => (
                                <Skeleton key={row} className="h-10 w-full" />
                            ))}
                        </div>
                    ) : (
                        <>
                            {step === 1 && (
                                <StepProject
                                    state={state}
                                    patch={patch}
                                    projects={options.projects}
                                    lockedProject={projectId === null ? null : project}
                                />
                            )}

                            {step === 2 && (
                                <StepWebsite
                                    state={state}
                                    patch={patch}
                                    categories={options.categories}
                                    chosen={site}
                                />
                            )}

                            {step === 3 && (
                                <StepContent
                                    state={state}
                                    patch={patch}
                                    site={site}
                                    publisherTask={publisherTask}
                                    article={article}
                                    onArticle={setArticle}
                                    image={image}
                                    onImage={setImage}
                                />
                            )}

                            {step === 4 && (
                                <StepReview
                                    state={state}
                                    patch={patch}
                                    project={project}
                                    site={site}
                                    wallet={options.wallet}
                                    article={article}
                                />
                            )}
                        </>
                    )}

                    {blocked !== null && step < 4 && (
                        <p className="text-sm text-ink-500">{blocked}</p>
                    )}
                </div>
            </Modal>

            {/* Closing mid-flow is a fork, not a confirmation: one branch keeps
                the work and one throws it away, and neither is obviously the
                default. So both are named buttons and neither is destructive
                by accident. */}
            <Modal
                open={confirmClose}
                onClose={() => setConfirmClose(false)}
                size="sm"
                title="Save this as a draft?"
                description="You can pick it up again from the Continue card on your dashboard."
                footer={
                    <>
                        <Button
                            variant="secondary"
                            onClick={() => {
                                void fetch('/posts/wizard/draft', {
                                    method: 'DELETE',
                                    headers: {
                                        Accept: 'application/json',
                                        'X-CSRF-TOKEN':
                                            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                                                ?.content ?? '',
                                    },
                                    credentials: 'same-origin',
                                }).catch(() => undefined);

                                close(true);
                            }}
                        >
                            Discard it
                        </Button>

                        <Button
                            onClick={() => {
                                // Saved before closing rather than left to the
                                // next tick, which will not come — the modal is
                                // about to unmount and take its interval with it.
                                void save().finally(() => close(true));
                            }}
                        >
                            Save as draft
                        </Button>
                    </>
                }
            >
                <p className="flex items-center gap-2 text-base text-ink-700">
                    {submitting !== null && <Spinner size={14} />}
                    Nothing has been bought yet. A draft keeps everything you have entered so far.
                </p>
            </Modal>
        </>
    );
}

/**
 * Why the current step cannot be left, in words.
 *
 * Returned as a sentence rather than a boolean so the disabled button can say
 * what it is waiting for. A Continue that is grey for no stated reason is a
 * dead end somebody has to guess their way out of.
 */
function blockerFor(step: number, state: PostWizardState, projectUrl: string | null): string | null {
    if (step === 1) {
        if (state.projectId === '') return 'Choose a project to file this post under.';

        const hasSaved = state.landingPageId !== '' && state.landingPageId !== 'manual';

        if (!hasSaved && (state.anchorText.trim() === '' || state.targetUrl.trim() === '')) {
            return 'Choose a saved landing page, or enter an anchor and a URL.';
        }

        // The domain warning does not block: pointing at a microsite is a real
        // thing to want, and only the advertiser knows which case this is.
        void domainMismatch(state.targetUrl, projectUrl);
    }

    if (step === 2 && state.websiteId === '') return 'Choose a website to place this on.';

    return null;
}

/** How far ahead the step indicator may be clicked. */
function furthestFor(state: PostWizardState): number {
    if (state.projectId === '') return 1;
    if (state.websiteId === '') return 2;

    return 4;
}
