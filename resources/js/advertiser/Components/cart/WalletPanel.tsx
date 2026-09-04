import { Button, WalletIcon } from '@shared/ui';
import { money } from '@shared/lib/format';
import type { CartWallet } from '@shared/types/cart';

interface Props {
    wallet: CartWallet;
    totalCents: number;
    onTopUp: () => void;
}

/**
 * The balance, and the gap.
 *
 * When there is enough this is one quiet line. When there is not, it names the
 * exact shortfall and puts the fix next to it — because "insufficient funds" at
 * the end of a three-step checkout is where advertisers abandon orders, and the
 * arithmetic is something the page can do for them here instead.
 */
export function WalletPanel({ wallet, totalCents, onTopUp }: Props) {
    const short = Math.max(0, totalCents - wallet.availableCents);

    return (
        <div className="flex flex-col gap-2 border-t border-subtle pt-4">
            <div className="flex items-baseline justify-between gap-3">
                <span className="flex items-center gap-1.5 text-base text-ink-700">
                    <WalletIcon size={14} />
                    Available balance
                </span>
                <span className="num text-base font-medium text-ink-900">{money(wallet.availableCents)}</span>
            </div>

            {wallet.frozenCents > 0 && (
                <p className="num text-xs text-ink-500">
                    {money(wallet.frozenCents)} is frozen against orders already placed.
                </p>
            )}

            {short > 0 && (
                <>
                    <p className="num rounded-card bg-warning-bg px-3 py-2 text-sm font-medium text-warning">
                        You need {money(short)} more
                    </p>

                    <Button variant="secondary" onClick={onTopUp}>
                        Top up balance
                    </Button>
                </>
            )}
        </div>
    );
}
