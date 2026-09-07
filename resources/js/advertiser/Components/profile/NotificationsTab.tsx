import { useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { Alert, Input, LockIcon, Switch, Tooltip } from '@shared/ui';
import { cn } from '@shared/lib/cn';
import { useUnsavedGuard } from '@shared/lib/useUnsavedGuard';
import { date } from '@shared/lib/format';
import type { NotificationMatrix, NotificationsState } from '@shared/types/profile';
import { SaveBar } from './SaveBar';

export function NotificationsTab({ notifications }: { notifications: NotificationsState }) {
    const initial = useMemo(
        () => ({
            matrix: notifications.matrix,
            paused_until: notifications.pausedUntil ?? '',
        }),
        [notifications],
    );

    const form = useForm(initial);
    const dirty = JSON.stringify(form.data) !== JSON.stringify(initial);

    useUnsavedGuard(dirty);

    const paused = form.data.paused_until !== '';

    const setCell = (event: string, channel: 'email' | 'in_app' | 'push', value: boolean) => {
        // The row is spread with a default rather than assumed present: the
        // server sends a cell for every event, but a matrix keyed by a string
        // is one renamed enum case away from a silent undefined.
        const row = form.data.matrix[event] ?? { email: false, in_app: false, push: false };

        const next: NotificationMatrix = {
            ...form.data.matrix,
            [event]: { ...row, [channel]: value },
        };

        form.setData('matrix', next);
    };

    return (
        <div className="flex min-w-0 flex-col gap-6">
            {/* Stated plainly and up front, because the alternative is somebody
                switching everything off, missing a refund notice, and being
                right to be annoyed about it. */}
            <Alert tone="info" title="Some emails cannot be turned off">
                Anything that records your money moving or an order changing state — a placement going live or
                being rejected, a top-up, a refund — is sent by email whatever you choose here. Those are
                receipts, not marketing, and an account that can silence them is an account where money moves in
                the dark.
            </Alert>

            <section className="rounded-card border border-subtle bg-card p-5 shadow-card">
                <h2 className="font-sora text-md font-semibold text-ink-900">What reaches you</h2>

                {/* Two layouts, one state.

                    A 10x3 grid does not survive a 390px screen: at that width
                    the switches sit entirely outside the viewport with nothing
                    to suggest a sideways scroll, so the tab renders as a list
                    of event names and nothing else. The phone gets a stack
                    instead, and the table appears from md up. */}
                <div className="mt-4 flex flex-col gap-3 md:hidden">
                    {notifications.events.map((event) => (
                        <div key={event.value} className="rounded-card border border-subtle p-4">
                            <p className="text-base text-ink-900">{event.label}</p>
                            <p className="mt-0.5 text-sm text-ink-500">{event.description}</p>

                            <div className="mt-3 flex flex-col gap-2 border-t border-subtle pt-3">
                                {notifications.channels.map((channel) => (
                                    <Cell
                                        key={channel.value}
                                        event={event}
                                        channel={channel}
                                        matrix={form.data.matrix}
                                        paused={paused}
                                        onChange={setCell}
                                        stacked
                                    />
                                ))}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="relative mt-4 hidden overflow-x-auto md:block">
                    <table className="w-full border-collapse text-left" style={{ minWidth: '640px' }}>
                        <thead>
                            <tr>
                                <th scope="col" className="border-b border-subtle px-3 py-2 text-sm font-medium text-ink-500">
                                    Event
                                </th>
                                {notifications.channels.map((channel) => (
                                    <th
                                        key={channel.value}
                                        scope="col"
                                        className="w-[120px] border-b border-subtle px-3 py-2 text-center text-sm font-medium text-ink-500"
                                    >
                                        {channel.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>

                        <tbody>
                            {notifications.events.map((event) => (
                                <tr key={event.value} className="border-b border-subtle last:border-0">
                                    <th scope="row" className="px-3 py-3 text-left font-normal">
                                        <span className="block text-base text-ink-900">{event.label}</span>
                                        <span className="block text-sm text-ink-500">{event.description}</span>
                                    </th>

                                    {notifications.channels.map((channel) => (
                                        <td key={channel.value} className="px-3 py-3 text-center">
                                            <Cell
                                                event={event}
                                                channel={channel}
                                                matrix={form.data.matrix}
                                                paused={paused}
                                                onChange={setCell}
                                            />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="rounded-card border border-subtle bg-card p-5 shadow-card">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <h2 className="font-sora text-md font-semibold text-ink-900">
                            Pause everything non-essential
                        </h2>
                        <p className="mt-0.5 max-w-prose text-sm text-ink-500">
                            Holidays, or a quiet fortnight. Receipts still arrive.
                        </p>
                    </div>

                    <Switch
                        label="Pause"
                        checked={paused}
                        onCheckedChange={(next) =>
                            form.setData(
                                'paused_until',
                                // A default a fortnight out, rather than making
                                // somebody pick a date before they can see what
                                // the switch does.
                                next ? new Date(Date.now() + 14 * 86_400_000).toISOString().slice(0, 10) : '',
                            )
                        }
                    />
                </div>

                {paused && (
                    <div className="mt-4 max-w-[220px]">
                        <Input
                            label="Until"
                            type="date"
                            min={new Date(Date.now() + 86_400_000).toISOString().slice(0, 10)}
                            value={form.data.paused_until}
                            error={form.errors.paused_until}
                            onChange={(event) => form.setData('paused_until', event.target.value)}
                        />
                    </div>
                )}

                {paused && form.data.paused_until !== '' && (
                    <p className="mt-3 rounded-card bg-sunken px-4 py-3 text-base text-ink-700">
                        Quiet until <span className="font-medium text-ink-900">{date(form.data.paused_until)}</span>,
                        then everything above resumes as it is set. Receipts about money and orders keep coming
                        throughout.
                    </p>
                )}
            </section>

            <SaveBar
                dirty={dirty}
                processing={form.processing}
                onReset={() => form.setData(initial)}
                onSave={() => {
                    // transform() returns void in this Inertia version, so it
                    // cannot be chained onto the patch — two statements, not one.
                    form.transform((data) => ({
                        matrix: data.matrix,
                        // An empty date field means "not paused", and the
                        // server's `nullable|date` rule rejects "".
                        paused_until: data.paused_until === '' ? null : data.paused_until,
                    }));

                    form.patch('/profile/notifications', { preserveScroll: true });
                }}
            />
        </div>
    );
}

/**
 * One switch, rendered the same way in the table and in the phone stack.
 *
 * `stacked` only decides whether the channel name is beside the control or
 * carried by the column header — the locked state, the paused dimming and the
 * accessible label are the same in both.
 */
function Cell({
    event,
    channel,
    matrix,
    paused,
    onChange,
    stacked = false,
}: {
    event: NotificationsState['events'][number];
    channel: NotificationsState['channels'][number];
    matrix: NotificationMatrix;
    paused: boolean;
    onChange: (event: string, channel: 'email' | 'in_app' | 'push', value: boolean) => void;
    stacked?: boolean;
}) {
    const key = channel.value as 'email' | 'in_app' | 'push';
    const locked = event.transactional && channel.value === 'email';
    const on = matrix[event.value]?.[key] ?? false;

    const control = locked ? (
        <Tooltip content="Always sent — this one is a receipt.">
            <span className="inline-flex items-center gap-1.5 text-sm text-ink-500">
                <LockIcon size={13} />
                On
            </span>
        </Tooltip>
    ) : (
        <span className={cn('inline-flex', paused && 'opacity-40')}>
            <Switch
                label={`${event.label}, ${channel.label}`}
                hideLabel
                checked={on}
                onCheckedChange={(next) => onChange(event.value, key, next)}
            />
        </span>
    );

    if (!stacked) return control;

    return (
        <span className="flex items-center justify-between gap-3">
            <span className="text-base text-ink-700">{channel.label}</span>
            {control}
        </span>
    );
}
