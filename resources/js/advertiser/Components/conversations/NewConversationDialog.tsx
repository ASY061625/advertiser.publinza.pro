import { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button, Input, Modal, Select, Textarea } from '@shared/ui';
import type { ComposerOptions } from '@shared/types/conversations';

type SubjectKind = 'post' | 'website';

/**
 * Opens a thread.
 *
 * The first field is what it is about, not what it says. A message with no
 * subject attached lands in a queue where somebody has to ask "which site?"
 * before they can answer anything — which is a whole round trip spent on
 * context the sender already had.
 */
export function NewConversationDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
    const [options, setOptions] = useState<ComposerOptions | null>(null);
    const [kind, setKind] = useState<SubjectKind>('post');

    const form = useForm({ subject: '', body: '', post_id: '', website_id: '' });

    // Fetched on open rather than shipped with every page: this is a list of
    // every post the advertiser has, and the dialog is opened rarely.
    useEffect(() => {
        if (!open || options !== null) return;

        let current = true;

        void fetch('/conversations/new/options', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => response.json() as Promise<ComposerOptions>)
            .then((payload) => {
                if (!current) return;

                setOptions(payload);
                // Somebody with no posts yet can only be asking about a site.
                if (payload.posts.length === 0) setKind('website');
            })
            .catch(() => undefined);

        return () => {
            current = false;
        };
    }, [open, options]);

    const submit = () => {
        form.transform((data) => ({
            subject: data.subject,
            body: data.body,
            post_id: kind === 'post' && data.post_id !== '' ? Number(data.post_id) : null,
            website_id: kind === 'website' && data.website_id !== '' ? Number(data.website_id) : null,
        }));

        form.post('/conversations', {
            // preserveState, so a rejected subject line comes back with the
            // message still in the box. `false` here remounts the dialog and
            // throws away both the errors and everything typed.
            preserveState: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="md"
            title="New conversation"
            description="Pick what this is about, and we'll keep the thread filed against it."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={submit} disabled={form.processing}>
                        {form.processing ? 'Sending…' : 'Send message'}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-4">
                <Select
                    label="This is about"
                    value={kind}
                    onChange={(event) => setKind(event.target.value as SubjectKind)}
                    options={[
                        { value: 'post', label: 'One of my posts' },
                        { value: 'website', label: 'A website' },
                    ]}
                    hint={
                        options !== null && options.posts.length === 0
                            ? 'You have no posts yet, so a website is the only subject available.'
                            : undefined
                    }
                    disabled={options !== null && options.posts.length === 0}
                />

                {kind === 'post' ? (
                    <Select
                        label="Post"
                        value={form.data.post_id}
                        error={form.errors.post_id}
                        onChange={(event) => form.setData('post_id', event.target.value)}
                        options={[
                            { value: '', label: options === null ? 'Loading…' : 'Choose a post…' },
                            ...(options?.posts ?? []).map((post) => ({
                                value: String(post.id),
                                label: post.label,
                            })),
                        ]}
                    />
                ) : (
                    <Select
                        label="Website"
                        value={form.data.website_id}
                        error={form.errors.website_id}
                        onChange={(event) => form.setData('website_id', event.target.value)}
                        options={[
                            { value: '', label: options === null ? 'Loading…' : 'Choose a website…' },
                            ...(options?.websites ?? []).map((site) => ({
                                value: String(site.id),
                                label: site.domain,
                            })),
                        ]}
                    />
                )}

                <Input
                    label="Subject"
                    placeholder="Publication date for the March article"
                    value={form.data.subject}
                    error={form.errors.subject}
                    onChange={(event) => form.setData('subject', event.target.value)}
                />

                <Textarea
                    label="Message"
                    rows={5}
                    placeholder="What would you like to know?"
                    value={form.data.body}
                    error={form.errors.body}
                    onChange={(event) => form.setData('body', event.target.value)}
                />
            </div>
        </Modal>
    );
}
