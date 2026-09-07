import {
    ChartIcon,
    ChatIcon,
    ClockIcon,
    DangerIcon,
    DocumentIcon,
    DownloadIcon,
    InfoIcon,
    ReceiptIcon,
    SuccessIcon,
    TagIcon,
    WalletIcon,
} from '@shared/ui';
import { cn } from '@shared/lib/cn';
import type { NotificationIcon as IconName, NotificationTone } from '@shared/types/notifications';

const ICONS = {
    success: SuccessIcon,
    danger: DangerIcon,
    document: DocumentIcon,
    chat: ChatIcon,
    clock: ClockIcon,
    wallet: WalletIcon,
    receipt: ReceiptIcon,
    tag: TagIcon,
    chart: ChartIcon,
    download: DownloadIcon,
    info: InfoIcon,
} as const satisfies Record<IconName, unknown>;

/**
 * The tone rings, drawn from the status palette the rest of the app uses.
 *
 * A published placement is the same green here as it is in the post table. The
 * fills are the `--status-*-bg` tokens rather than new ones, so a change to the
 * palette reaches the drawer without anybody remembering to come here.
 */
const TONES = {
    success: 'bg-status-posted-bg text-status-posted-fg',
    danger: 'bg-status-rejected-bg text-status-rejected-fg',
    warning: 'bg-status-progress-bg text-status-progress-fg',
    gold: 'bg-status-refunded-bg text-status-refunded-fg',
    review: 'bg-status-review-bg text-status-review-fg',
    info: 'bg-status-new-bg text-status-new-fg',
} as const satisfies Record<NotificationTone, string>;

export function NotificationIcon({
    icon,
    tone,
    size = 'md',
}: {
    icon: IconName;
    tone: NotificationTone;
    size?: 'sm' | 'md';
}) {
    // Falls back rather than crashing: these values came out of a database row
    // that may predate the current vocabulary.
    const Icon = ICONS[icon] ?? InfoIcon;
    const fill = TONES[tone] ?? TONES.info;

    return (
        <span
            aria-hidden="true"
            className={cn(
                'flex shrink-0 items-center justify-center rounded-pill',
                size === 'sm' ? 'size-7' : 'size-9',
                fill,
            )}
        >
            <Icon size={size === 'sm' ? 13 : 16} />
        </span>
    );
}
