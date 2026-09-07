import { useEffect, useRef } from 'react';
import type { BroadcastNotification } from '@shared/types/notifications';
import type { EchoConfig } from '@shared/types/shell';

/**
 * Live notifications, and the browser notification that may follow one.
 *
 * Two things happen when a broadcast arrives: the caller re-reads its badge
 * counts, and — if the server said this type may interrupt and the browser has
 * been given permission — a browser notification is raised.
 *
 * `push` is the *server's* answer, computed against the account's preference.
 * The browser only knows whether permission was granted, which is a different
 * question from whether this person asked to be interrupted about this.
 *
 * The honest limit: this raises a notification from a page that is open. A
 * notification that arrives while every tab is closed needs a service worker
 * and a Web Push subscription, which this app does not have — so the preference
 * is real and works, and its reach is "while Publinza is open somewhere".
 */
export function useLiveNotifications(
    echoConfig: EchoConfig | null,
    userId: number | null,
    onArrived: () => void,
) {
    // Held in a ref so a re-render with a new closure does not tear down and
    // rebuild the socket.
    const arrived = useRef(onArrived);
    arrived.current = onArrived;

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

                echo.private(`advertiser.${userId}`).notification((payload: BroadcastNotification) => {
                    arrived.current();
                    raise(payload);
                });

                teardown = () => {
                    echo.leave(`advertiser.${userId}`);
                    echo.disconnect();
                };
            } catch {
                // No socket. The shell's 60-second count poll still moves the
                // badge; only the instant arrival is lost.
            }
        })();

        return () => {
            cancelled = true;
            teardown?.();
        };
    }, [echoConfig, userId]);
}

function raise(payload: BroadcastNotification): void {
    if (payload.push !== true) return;

    try {
        if (typeof Notification === 'undefined' || Notification.permission !== 'granted') return;

        const shown = new Notification(payload.title, {
            body: payload.body,
            // Tagged by type, so a burst of the same kind replaces itself
            // rather than stacking five identical banners.
            tag: payload.type,
        });

        shown.onclick = () => {
            window.focus();
            window.location.href = payload.href;
        };
    } catch {
        // Some browsers throw on `new Notification` outside a service worker
        // (Android Chrome, notably). Nothing to do about it and nothing worth
        // showing the user — the in-app badge already moved.
    }
}

/**
 * Asks for permission, once, when somebody first opens the drawer having
 * switched browser push on.
 *
 * Deliberately not on page load: a permission prompt that appears before
 * anybody has asked for anything is the prompt everybody denies, and a denial
 * is permanent.
 */
export async function requestPushPermission(): Promise<NotificationPermission> {
    if (typeof Notification === 'undefined') return 'denied';

    if (Notification.permission !== 'default') return Notification.permission;

    try {
        return await Notification.requestPermission();
    } catch {
        return 'denied';
    }
}
