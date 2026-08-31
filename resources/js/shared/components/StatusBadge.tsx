import { STATUS_STYLES } from '@shared/lib/status';
import type { StatusKey } from '@shared/types';

export function StatusBadge({ status }: { status: StatusKey }) {
    const style = STATUS_STYLES[status];

    return (
        <span
            className="inline-flex items-center rounded-pill px-2.5 py-1 text-xs font-medium"
            style={{ backgroundColor: style.fill, color: style.text }}
        >
            {style.label}
        </span>
    );
}
