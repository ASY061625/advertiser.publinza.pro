import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button, Modal } from '@shared/ui';
import { date } from '@shared/lib/format';
import type { Announcement } from '@shared/types/notifications';
import { ChangelogChip } from './ChangelogChip';

/**
 * The one-time modal a major release earns.
 *
 * Shown once per account, dismissed by "Got it", and never shown again — the
 * acknowledgement is its own timestamp on the user, kept apart from the drawer's
 * "last seen" so that glancing at the drawer does not silently dismiss an
 * announcement nobody read.
 *
 * Closed optimistically: the modal goes the moment it is dismissed and the write
 * follows. A failed write means it appears once more, which is a far better
 * failure than a modal that will not close.
 */
export function AnnouncementModal({ announcement }: { announcement: Announcement }) {
    const [open, setOpen] = useState(true);

    const dismiss = () => {
        setOpen(false);
        router.post('/whats-new/acknowledge', {}, { preserveScroll: true, preserveState: true });
    };

    return (
        <Modal
            open={open}
            onClose={dismiss}
            size="md"
            title={announcement.title}
            description={announcement.publishedAt === null ? undefined : date(announcement.publishedAt)}
            footer={<Button onClick={dismiss}>Got it</Button>}
        >
            <div className="flex flex-col gap-4">
                <div>
                    <ChangelogChip type={announcement.type} label={announcement.typeLabel} />
                </div>

                {announcement.imageUrl !== null && (
                    <img
                        src={announcement.imageUrl}
                        alt=""
                        className="w-full rounded-card border border-subtle"
                    />
                )}

                {/* Sanitised server-side against a fixed tag set — see
                    ChangelogHtml. */}
                <div
                    className="prose-changelog"
                    dangerouslySetInnerHTML={{ __html: announcement.body }}
                />
            </div>
        </Modal>
    );
}
