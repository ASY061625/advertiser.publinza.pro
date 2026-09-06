import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import type { EchoConfig } from '@shared/types/shell';

const POLL_MS = 20_000;

/** How long "Publinza is typing…" survives without another event. */
const TYPING_TTL_MS = 6_000;

/**
 * Keeps an open thread live.
 *
 * Echo when a broadcaster is configured, a 20-second poll when it is not — the
 * same arrangement the header counts use, and for the same reason: with
 * BROADCAST_CONNECTION unset the poll is the whole mechanism, not a degraded
 * one. Faster than the header's 60 seconds because a reply somebody is waiting
 * on is worth more than a badge.
 *
 * The event carries a thread id and nothing else. Everything on screen is
 * re-read through the controller, so a stale event cannot paint a message that
 * has since been edited, and the policy is still what decides what this browser
 * is allowed to see.
 */
export function useConversationRealtime(echoConfig: EchoConfig | null, userId: number | null, threadId: number | null) {
    const [typing, setTyping] = useState<string | null>(null);
    const typingTimer = useRef<number>();
    // Read inside the listener, which is registered once — a stale closure here
    // would keep reloading whichever thread was open when the socket connected.
    const openThread = useRef(threadId);

    openThread.current = threadId;

    useEffect(() => () => window.clearTimeout(typingTimer.current), []);

    // The typing indicator belongs to one thread. Switching threads has to drop
    // it, or the previous conversation's dots follow the reader across.
    useEffect(() => setTyping(null), [threadId]);

    useEffect(() => {
        if (echoConfig !== null) return;

        const timer = window.setInterval(() => {
            if (document.visibilityState !== 'visible') return;

            // `shell` too: a reply that arrives while the tab is open has to
            // move the header badge, not just the thread.
            router.reload({ only: ['thread', 'threads', 'counts', 'shell'] });
        }, POLL_MS);

        return () => window.clearInterval(timer);
    }, [echoConfig]);

    // Echo is imported dynamically, so the ~40KB of Echo and Pusher never
    // reaches an installation with no broadcaster configured.
    useEffect(() => {
        if (echoConfig === null || userId === null) return;

        let cancelled = false;
        let teardown: (() => void) | undefined;

        void (async () => {
            try {
                const [{ default: Echo }, { default: Pusher }] = await Promise.all([
                    import('laravel-echo'),
                    import('pusher-js'),
                ]);

                if (cancelled) return;

                const echo = new Echo({
                    broadcaster: 'reverb',
                    client: Pusher,
                    key: echoConfig.key,
                    wsHost: echoConfig.host,
                    wsPort: echoConfig.port,
                    wssPort: echoConfig.port,
                    forceTLS: echoConfig.scheme === 'https',
                    enabledTransports: ['ws', 'wss'],
                });

                const channel = echo.private(`advertiser.${userId}`);

                channel.listen('.conversation.activity', () =>
                    // Both, always: a reply in a thread that is not open still
                    // moves that row to the top of the list and lights its pill.
                    router.reload({ only: ['thread', 'threads', 'counts', 'shell'] }),
                );

                channel.listen('.conversation.typing', (event: { threadId: number; name: string }) => {
                    if (event.threadId !== openThread.current) return;

                    setTyping(event.name);
                    window.clearTimeout(typingTimer.current);
                    // Expired locally rather than waiting for a "stopped"
                    // event: a teammate who closes the tab mid-sentence never
                    // sends one, and the dots would sit there forever.
                    typingTimer.current = window.setTimeout(() => setTyping(null), TYPING_TTL_MS);
                });

                teardown = () => {
                    echo.leave(`advertiser.${userId}`);
                    echo.disconnect();
                };
            } catch {
                // Echo failed to load or connect. Fall back to the poll rather
                // than leaving the thread frozen.
                if (cancelled) return;

                const timer = window.setInterval(() => {
                    router.reload({ only: ['thread', 'threads', 'counts', 'shell'] });
                }, POLL_MS);

                teardown = () => window.clearInterval(timer);
            }
        })();

        return () => {
            cancelled = true;
            teardown?.();
        };
    }, [echoConfig, userId]);

    return { typing };
}
