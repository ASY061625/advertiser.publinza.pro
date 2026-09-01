import { useCallback, useEffect, useRef, useState } from 'react';
import type { EchoConfig, ShellCounts } from '@shared/types/shell';

const POLL_MS = 60_000;

/**
 * Keeps the header badge counts current.
 *
 * Echo when a broadcaster is configured, a 60-second poll when it is not. The
 * poll is not a degraded mode — with BROADCAST_CONNECTION unset it is the whole
 * mechanism, and the app is fully usable that way.
 *
 * Events carry no numbers, only which scopes moved: the client re-reads the
 * counts, so two tabs cannot disagree because events arrived out of order.
 */
export function useShellCounts(initial: ShellCounts, echoConfig: EchoConfig | null, userId: number | null) {
    const [counts, setCounts] = useState(initial);
    const inFlight = useRef(false);

    // Server-rendered counts win on navigation: they are newer than anything
    // this hook has fetched.
    useEffect(() => setCounts(initial), [initial]);

    const refresh = useCallback(async () => {
        if (inFlight.current) return;
        inFlight.current = true;

        try {
            const response = await fetch('/shell/counts', {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (response.ok) setCounts((await response.json()) as ShellCounts);
        } catch {
            // Offline or a dropped request. The next tick tries again; showing
            // a stale badge is better than showing an error over a count.
        } finally {
            inFlight.current = false;
        }
    }, []);

    // Poll fallback. Skipped while the tab is hidden, so a pinned background tab
    // does not spend the day polling.
    useEffect(() => {
        if (echoConfig !== null) return;

        const timer = window.setInterval(() => {
            if (document.visibilityState === 'visible') void refresh();
        }, POLL_MS);

        return () => window.clearInterval(timer);
    }, [echoConfig, refresh]);

    // Echo. Imported dynamically so the ~40KB of Echo and Pusher never reaches
    // an installation that has no broadcaster configured.
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

                echo.private(`advertiser.${userId}`).listen('.shell.counts', () => void refresh());

                teardown = () => {
                    echo.leave(`advertiser.${userId}`);
                    echo.disconnect();
                };
            } catch {
                // Echo failed to load or connect. The poll below covers it.
                if (!cancelled) {
                    const timer = window.setInterval(() => void refresh(), POLL_MS);
                    teardown = () => window.clearInterval(timer);
                }
            }
        })();

        return () => {
            cancelled = true;
            teardown?.();
        };
    }, [echoConfig, userId, refresh]);

    return { counts, refresh };
}
