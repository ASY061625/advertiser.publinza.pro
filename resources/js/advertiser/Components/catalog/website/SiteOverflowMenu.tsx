import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Button,
    CopyIcon,
    Dropdown,
    HeartIcon,
    IconButton,
    Input,
    Modal,
    MoreIcon,
    Textarea,
    TrashIcon,
    WarningIcon,
    useToast,
} from '@shared/ui';
import type { CatalogRow } from '@shared/types/catalog';

/** Which confirmation is on screen, if any. */
type Dialog = 'blacklist' | 'report' | null;

/**
 * The four actions that are not "buy this".
 *
 * They live behind one control because they are rare, and rare next to a
 * primary button is how a mis-click becomes a hidden site. Two of them ask
 * first: blacklisting removes the site from every future search, and reporting
 * a problem opens a conversation a human will read — neither is something to
 * fire on a single click from a menu.
 */
export function SiteOverflowMenu({ site }: { site: CatalogRow }) {
    const [dialog, setDialog] = useState<Dialog>(null);
    const { toast } = useToast();

    function copyDomain() {
        navigator.clipboard
            ?.writeText(site.domain)
            .then(() => toast({ tone: 'success', title: `Copied ${site.domain}` }))
            .catch(() =>
                toast({
                    tone: 'danger',
                    title: 'Could not copy',
                    description: 'Your browser would not let us write to the clipboard.',
                }),
            );
    }

    return (
        <>
            <Dropdown
                trigger={
                    <IconButton
                        label={`More actions for ${site.domain}`}
                        variant="ghost"
                        size="sm"
                        icon={<MoreIcon size={18} />}
                    />
                }
                items={[
                    {
                        id: 'wishlist',
                        label: 'Add to wishlist',
                        icon: <HeartIcon size={14} />,
                        onSelect: () =>
                            router.post(
                                `/sites/${site.slug}/wishlist`,
                                {},
                                { preserveScroll: true, preserveState: true },
                            ),
                    },
                    {
                        id: 'copy',
                        label: 'Copy domain',
                        icon: <CopyIcon size={14} />,
                        onSelect: copyDomain,
                    },
                    {
                        id: 'report',
                        label: 'Report a problem',
                        icon: <WarningIcon size={14} />,
                        onSelect: () => setDialog('report'),
                    },
                    {
                        id: 'blacklist',
                        label: site.isBlacklisted ? 'Remove from blacklist' : 'Add to blacklist',
                        icon: <TrashIcon size={14} />,
                        destructive: !site.isBlacklisted,
                        onSelect: () =>
                            // Un-blacklisting needs no confirmation: it puts
                            // the site back, which is the reversible direction.
                            site.isBlacklisted
                                ? router.post(
                                      `/sites/${site.slug}/blacklist`,
                                      {},
                                      { preserveScroll: true, preserveState: true },
                                  )
                                : setDialog('blacklist'),
                    },
                ]}
            />

            <BlacklistDialog
                site={site}
                open={dialog === 'blacklist'}
                onClose={() => setDialog(null)}
            />
            <ReportDialog site={site} open={dialog === 'report'} onClose={() => setDialog(null)} />
        </>
    );
}

/**
 * Hiding a site, and why.
 *
 * The reason is optional and asked for anyway. Six months from now "too many
 * outbound links" is the difference between a blacklist somebody trusts and one
 * they rebuild from scratch because they cannot remember what any of it was
 * for.
 */
function BlacklistDialog({ site, open, onClose }: { site: CatalogRow; open: boolean; onClose: () => void }) {
    const [reason, setReason] = useState('');
    const [saving, setSaving] = useState(false);

    function submit() {
        setSaving(true);

        router.post(
            `/sites/${site.slug}/blacklist`,
            { reason: reason.trim() === '' ? null : reason.trim() },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setReason('');
                    onClose();
                },
                onFinish: () => setSaving(false),
            },
        );
    }

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="sm"
            title={`Hide ${site.domain}?`}
            description="It stops appearing in your catalog and in search results. You can undo this from your blacklist at any time."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button variant="danger" onClick={submit} loading={saving}>
                        Hide this site
                    </Button>
                </>
            }
        >
            <Input
                label="Why? (optional)"
                hint="Only you will see this."
                maxLength={190}
                value={reason}
                onChange={(event) => setReason(event.target.value)}
                placeholder="Too many outbound links"
            />
        </Modal>
    );
}

/**
 * Reporting a problem.
 *
 * Opens a conversation rather than filing a ticket somewhere the advertiser
 * cannot see it, which is why the button says so: a report that vanishes is
 * indistinguishable from a report nobody read.
 */
function ReportDialog({ site, open, onClose }: { site: CatalogRow; open: boolean; onClose: () => void }) {
    const [message, setMessage] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function submit() {
        setSaving(true);
        setError(null);

        router.post(
            `/sites/${site.slug}/report`,
            { message: message.trim() },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setMessage('');
                    onClose();
                },
                onError: (errors) => setError(errors.message ?? 'That could not be sent.'),
                onFinish: () => setSaving(false),
            },
        );
    }

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="sm"
            title={`Report a problem with ${site.domain}`}
            description="This opens a conversation in your inbox, so you will see the reply there."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={submit} loading={saving} disabled={message.trim().length < 10}>
                        Send report
                    </Button>
                </>
            }
        >
            <Textarea
                label="What is wrong?"
                error={error ?? undefined}
                maxLength={2000}
                showCount
                value={message}
                onChange={(event) => setMessage(event.target.value)}
                placeholder="The metrics look out of date — Ahrefs shows a DR of 12, not 46."
            />
        </Modal>
    );
}
