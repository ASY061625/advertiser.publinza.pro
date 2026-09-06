/** What a URL parameter can hold: the array form is a repeated `key[]`. */
export type QueryValue = string | number | boolean | null | (string | number)[];

export type BalanceTab = 'overview' | 'top-up' | 'transactions' | 'invoices';

export type TransactionKind =
    | 'deposit'
    | 'charge'
    | 'freeze'
    | 'unfreeze'
    | 'refund'
    | 'bonus'
    | 'adjustment';

export type TopUpMethodValue = 'saved_card' | 'new_card' | 'paypal' | 'bank_transfer';

/** Where a ledger row came from, resolved to something clickable. */
export interface LedgerSubject {
    label: string;
    href: string;
}

export interface LedgerRow {
    id: number;
    type: TransactionKind;
    typeLabel: string;
    /** Signed as the ledger stores it. The table colours on this sign. */
    amountCents: number;
    balanceAfterCents: number;
    frozenAfterCents: number;
    description: string | null;
    createdAt: string | null;
    subject: LedgerSubject | null;
}

export interface MonthPoint {
    iso: string;
    label: string;
    depositCents: number;
    spendCents: number;
}

export interface AutoTopUpState {
    enabled: boolean;
    /** False when the rule is on but missing a piece — a card, usually. */
    armed: boolean;
    thresholdCents: number | null;
    amountCents: number | null;
    paymentMethodId: number | null;
}

export interface BalanceOverview {
    availableCents: number;
    frozenCents: number;
    spent: { lifetimeCents: number; yearCents: number; year: number };
    frozenPosts: { count: number; href: string };
    series: MonthPoint[];
    recent: LedgerRow[];
    autoTopUp: AutoTopUpState;
}

export interface SavedCard {
    id: number;
    brand: string | null;
    lastFour: string | null;
    expMonth: number | null;
    expYear: number | null;
    isDefault: boolean;
    expired: boolean;
}

export interface BonusTier {
    atCents: number;
    percent: number;
}

export interface TopUpConfig {
    minimumCents: number;
    quickAmountsCents: number[];
    tiers: BonusTier[];
    bank: { beneficiary: string; iban: string; bic: string; bank_name: string };
    gatewayIsLive: boolean;
    /** Null when no publishable key is configured — the card form says so. */
    stripeKey: string | null;
}

export interface LedgerPage {
    rows: LedgerRow[];
    total: number;
    page: number;
    lastPage: number;
}

export interface LedgerFilters {
    types: string[];
    from: string | null;
    to: string | null;
    min: number | null;
    max: number | null;
    q: string | null;
}

export interface InvoiceRow {
    id: number;
    number: string;
    issuedAt: string | null;
    periodStart: string | null;
    periodEnd: string | null;
    totalCents: number;
    status: string;
}

export interface BillingDetails {
    name: string;
    company: string | null;
    address: string | null;
    country: string | null;
    vatNo: string | null;
    billingEmail: string | null;
    /** The fallback when billingEmail is unset. Shown, not silently used. */
    accountEmail: string;
}

export interface PendingTransfer {
    reference: string;
    amountCents: number;
    createdAt: string | null;
}

export interface BalancePageProps {
    tab: BalanceTab;
    overview: BalanceOverview;
    cards: SavedCard[];
    topUp: TopUpConfig;
    ledger: LedgerPage | null;
    filters: LedgerFilters;
    types: { value: string; label: string }[];
    invoices: InvoiceRow[] | null;
    billing: BillingDetails;
    pendingTransfers: PendingTransfer[];
    [key: string]: unknown;
}
