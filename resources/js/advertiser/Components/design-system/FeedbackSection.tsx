import { useState } from 'react';
import {
    Alert,
    Button,
    Drawer,
    Dropdown,
    EmptyState,
    IconButton,
    Modal,
    ProgressBar,
    SearchIcon,
    Skeleton,
    SkeletonText,
    TrashIcon,
    useToast,
    type ToastTone,
} from '@shared/ui';
import { Panel, Row, Section } from './Shell';

const TONES: ToastTone[] = ['info', 'success', 'warning', 'danger'];

export function FeedbackSection() {
    const { toast } = useToast();
    const [modal, setModal] = useState(false);
    const [drawer, setDrawer] = useState(false);

    return (
        <>
            <Section
                id="alert"
                title="Alert"
                note="Errors say what happened and what to do next. An alert stays on the page; a toast passes."
            >
                <Row label="Tones" stack>
                    <div className="flex w-full max-w-2xl flex-col gap-3">
                        <Alert tone="info" title="Sites are re-scored nightly">
                            Traffic and DR update at 02:00 UTC.
                        </Alert>
                        <Alert tone="success" title="Published" />
                        <Alert tone="warning" title="Your balance is low">
                            Top up to at least $500 to keep the four open orders moving.
                        </Alert>
                        <Alert
                            tone="danger"
                            title="Checkout failed"
                            action={
                                <Button size="sm" variant="secondary">
                                    Top up balance
                                </Button>
                            }
                            onDismiss={() => undefined}
                        >
                            Your available balance is $180.00 and the cart totals $640.00.
                        </Alert>
                    </div>
                </Row>
            </Section>

            <Section
                id="toast"
                title="Toast"
                note="A button labelled “Publish” produces a toast reading “Published”. Announced politely so it never interrupts a screen reader mid-sentence."
            >
                <Row label="Trigger">
                    {TONES.map((tone) => (
                        <Button
                            key={tone}
                            variant="secondary"
                            onClick={() =>
                                toast({
                                    tone,
                                    title: {
                                        info: 'Re-scoring started',
                                        success: 'Published',
                                        warning: 'Balance is low',
                                        danger: 'Checkout failed',
                                    }[tone],
                                    description: tone === 'danger' ? 'Available balance is $180.00.' : undefined,
                                })
                            }
                        >
                            Show {tone} toast
                        </Button>
                    ))}
                </Row>
            </Section>

            <Section id="progress" title="ProgressBar">
                <Row label="States" stack>
                    <div className="flex w-full max-w-md flex-col gap-5">
                        <ProgressBar label="Content review" value={64} showValue />
                        <ProgressBar label="Published" value={100} tone="success" showValue />
                        <ProgressBar label="Budget used" value={92} tone="warning" showValue />
                        <ProgressBar label="Over budget" value={100} tone="danger" showValue />
                        <ProgressBar label="Indeterminate" />
                    </div>
                </Row>
            </Section>

            <Section
                id="skeleton"
                title="Skeleton"
                note="Shimmer is a background animation, so reduced-motion flattens it to a static block."
            >
                <Row label="Shapes" stack>
                    <Panel>
                        <div className="flex items-center gap-4">
                            <Skeleton shape="circle" width="w-11" height="h-11" />
                            <div className="flex-1">
                                <SkeletonText lines={3} />
                            </div>
                        </div>
                        <div className="mt-5">
                            <Skeleton shape="block" height="h-20" />
                        </div>
                    </Panel>
                </Row>
            </Section>

            <Section
                id="empty"
                title="EmptyState"
                note="Illustration slot, one line of direction, one button. Never an apology."
            >
                <Row label="Default" stack>
                    <EmptyState
                        illustration={<SearchIcon size={32} />}
                        direction="No projects yet. Create one to start buying placements."
                        action={<Button>Create project</Button>}
                    />
                </Row>
                <Row label="Without action" stack>
                    <EmptyState direction="No messages on this order yet." />
                </Row>
            </Section>

            <Section
                id="overlays"
                title="Modal, Drawer and Dropdown"
                note="Both overlays trap focus, restore it on close, lock body scroll and close on Escape or an outside press."
            >
                <Row label="Triggers">
                    <Button variant="secondary" onClick={() => setModal(true)}>
                        Open modal
                    </Button>
                    <Button variant="secondary" onClick={() => setDrawer(true)}>
                        Open drawer
                    </Button>
                    <Dropdown
                        trigger={<IconButton label="Row actions" variant="secondary" icon={<SearchIcon size={16} />} />}
                        items={[
                            { id: 'view', label: 'View site details', onSelect: () => undefined },
                            { id: 'save', label: 'Save for later', onSelect: () => undefined },
                            {
                                id: 'block',
                                label: 'Block this site',
                                destructive: true,
                                icon: <TrashIcon size={15} />,
                                onSelect: () => undefined,
                            },
                        ]}
                    />
                </Row>

                <Modal
                    open={modal}
                    onClose={() => setModal(false)}
                    title="Remove 3 sites from cart?"
                    description="This does not cancel anything already ordered."
                    footer={
                        <>
                            <Button variant="secondary" onClick={() => setModal(false)}>
                                Keep them
                            </Button>
                            <Button variant="danger" onClick={() => setModal(false)}>
                                Remove sites
                            </Button>
                        </>
                    }
                >
                    <p>The cart total will drop from $2,860.00 to $1,240.00.</p>
                </Modal>

                <Drawer
                    open={drawer}
                    onClose={() => setDrawer(false)}
                    title="techcrunch.com"
                    description="Technology · English"
                    footer={
                        <>
                            <Button variant="secondary" onClick={() => setDrawer(false)}>
                                Close
                            </Button>
                            <Button onClick={() => setDrawer(false)}>Add to cart</Button>
                        </>
                    }
                >
                    <p className="mb-4">
                        480px wide, sliding in over 180ms. Used for detail views that should not lose the list behind
                        them.
                    </p>
                    <SkeletonText lines={6} />
                </Drawer>
            </Section>
        </>
    );
}
