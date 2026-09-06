import { useCallback, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import type { ConversationMessage } from '@shared/types/conversations';

/** A message the browser has drawn but the server has not confirmed. */
export interface PendingMessage extends ConversationMessage {
    /** Kept so a retry re-sends the same files, not just the same words. */
    files: File[];
}

/**
 * Optimistic sending.
 *
 * The message is on screen before the request leaves, because a composer that
 * waits for a round trip before showing what you typed feels broken on a train.
 * Three things make that honest rather than a lie:
 *
 *  - Every send carries a client token, minted once and reused on every retry,
 *    so a retry of a request that actually succeeded is a no-op server-side
 *    rather than a duplicate message.
 *  - A failure marks the message failed instead of removing it. Deleting what
 *    somebody wrote because the network dropped is how you lose their words.
 *  - The pending copy is dropped only once the server's own version of it
 *    arrives in the page props, matched on that same token — so there is never
 *    a frame with both, and never a frame with neither.
 *
 * Sent with fetch rather than through Inertia's router, and this is the whole
 * reason: a visit's `onError` fires for a 422 and for nothing else, so a
 * dropped connection — the case the retry exists for — resolved as a silent
 * success and left "Sending…" on screen forever. fetch tells us what actually
 * happened; the reload afterwards is what refreshes the panes.
 */
export function useSendMessage(threadId: number | null) {
    const [pending, setPending] = useState<PendingMessage[]>([]);
    const inFlight = useRef(new Set<string>());

    const post = useCallback(
        async (message: PendingMessage) => {
            const token = message.clientToken;

            if (threadId === null || token === null || inFlight.current.has(token)) return;

            inFlight.current.add(token);

            const body = new FormData();
            body.append('body', message.body);
            body.append('client_token', token);

            for (const file of message.files) body.append('attachments[]', file);

            try {
                const response = await fetch(`/conversations/${threadId}/messages`, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf(),
                    },
                    credentials: 'same-origin',
                    body,
                });

                if (!response.ok) throw new Error(String(response.status));

                // The thread and the list both move: the new message, and the
                // row's excerpt and its position in the inbox.
                router.reload({ only: ['thread', 'threads', 'counts', 'shell'] });
            } catch {
                setPending((current) =>
                    current.map((m) => (m.clientToken === token ? { ...m, failed: true } : m)),
                );
            } finally {
                inFlight.current.delete(token);
            }
        },
        [threadId],
    );

    const send = useCallback(
        (text: string, files: File[]) => {
            const clientToken = newToken();

            const message: PendingMessage = {
                // Negative, so it can never collide with a server id — this is
                // a React key and an identity, and a clash would swap two
                // messages' contents as the real ones arrive.
                id: -Date.now(),
                senderType: 'user',
                senderName: 'You',
                body: text,
                createdAt: new Date().toISOString(),
                delivered: false,
                readAt: null,
                clientToken,
                // Local previews, so an attached screenshot appears in the
                // bubble immediately rather than after the upload.
                attachments: files.map((file, index) => ({
                    id: -index - 1,
                    name: file.name,
                    sizeBytes: file.size,
                    mimeType: file.type,
                    isImage: file.type.startsWith('image/'),
                    url: URL.createObjectURL(file),
                })),
                files,
            };

            setPending((current) => [...current, message]);
            void post(message);
        },
        [post],
    );

    const retry = useCallback(
        (message: ConversationMessage) => {
            setPending((current) => {
                const found = current.find((m) => m.clientToken === message.clientToken);

                if (found === undefined) return current;

                void post({ ...found, failed: false });

                return current.map((m) => (m.clientToken === found.clientToken ? { ...m, failed: false } : m));
            });
        },
        [post],
    );

    /**
     * Drops the optimistic copies the server has now sent back.
     *
     * Matched on the client token rather than on position or text: two
     * identical "thanks" a minute apart are different messages, and the token
     * is the only thing that tells them apart.
     */
    const reconcile = useCallback((confirmed: ConversationMessage[]) => {
        const tokens = new Set(confirmed.map((message) => message.clientToken).filter(Boolean));

        setPending((current) => {
            const next = current.filter((message) => !tokens.has(message.clientToken));

            if (next.length === current.length) return current;

            // Release the preview URLs of everything that just landed for real,
            // or a long session leaks every image the advertiser ever attached.
            for (const message of current) {
                if (!tokens.has(message.clientToken)) continue;

                for (const file of message.attachments) URL.revokeObjectURL(file.url);
            }

            return next;
        });
    }, []);

    const reset = useCallback(() => setPending([]), []);

    return { pending, send, retry, reconcile, reset };
}

/**
 * crypto.randomUUID is only defined in a secure context. Production is HTTPS,
 * but a plain-HTTP staging box would otherwise throw here — and losing the
 * token loses the protection against a double post, which is the one thing
 * this must not do quietly.
 */
function newToken(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
}

function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}
