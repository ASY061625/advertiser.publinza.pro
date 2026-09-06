import { Link } from '@inertiajs/react';
import { PlusIcon, Tooltip, WalletIcon } from '@shared/ui';
import { money } from '@shared/lib/format';

interface BalancePillProps {
    availableCents: number;
    frozenCents: number;
}

/**
 * The balance, always in the header, with a way to add to it.
 *
 * A link rather than a modal. It used to open a two-field form that posted a
 * top-up directly, which meant money moved without anybody choosing a payment
 * method or seeing what it would cost — and the balance page had to grow a
 * second, better version of the same form beside it. One place adds funds now,
 * and this is the door to it.
 */
export function BalancePill({ availableCents, frozenCents }: BalancePillProps) {
    return (
        <Tooltip
            content={`${money(availableCents)} available · ${money(frozenCents)} frozen against open orders`}
            side="bottom"
        >
            <span className="flex h-9 items-center gap-2 rounded-pill bg-gold-subtle pl-3 pr-1 text-[#B45309]">
                <WalletIcon size={15} className="shrink-0" />

                <Link href="/balance" className="num text-base font-medium hover:underline">
                    {money(availableCents)}
                </Link>

                <Link
                    href="/balance?tab=top-up"
                    aria-label="Top up balance"
                    className="hover:bg-gold/20 flex size-7 items-center justify-center rounded-pill transition-colors duration-fast"
                >
                    <PlusIcon size={15} />
                </Link>
            </span>
        </Tooltip>
    );
}
