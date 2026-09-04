export interface CartWarning {
    kind: 'language' | 'topic' | 'duplicate' | 'unavailable';
    message: string;
}

export interface CartStagedArticle {
    title: string | null;
    wordCount: number | null;
    fileName: string | null;
}

export interface CartLine {
    id: number;
    website: {
        id: number | null;
        slug: string | null;
        domain: string;
        publicationHours: number;
        minWords: number;
    };
    projectId: number | null;
    folder: { id: number; name: string } | null;
    serviceType: string;
    serviceLabel: string;
    contentMode: string;
    contentLabel: string;
    anchorText: string | null;
    targetUrl: string | null;
    express: boolean;
    baseCents: number;
    /** Zero unless the publisher is writing it. */
    writingFeeCents: number;
    /** Zero unless express was asked for. */
    expressFeeCents: number;
    totalCents: number;
    /** What the publisher offers, whether or not this line takes it. */
    fees: { writingCents: number; expressCents: number };
    /** What this line was quoted when it was added, present only when the
     *  publisher's price has moved since. The line is charged at the new one. */
    quotedCents: number | null;
    article: CartStagedArticle | null;
    warnings: CartWarning[];
}

export interface CartGroup {
    /** The project id, or 0 for lines with no project. */
    id: number;
    project: { id: number; name: string; color: string | null } | null;
    itemCount: number;
    subtotalCents: number;
    items: CartLine[];
}

export interface CartTotals {
    subtotalCents: number;
    writingFeesCents: number;
    expressFeesCents: number;
    discountCents: number;
    totalCents: number;
}

export interface CartPromo {
    code: string;
    description: string | null;
    discountCents: number;
    /** The code stopped being redeemable while it sat on the cart. */
    expired: boolean;
    belowMinimum: boolean;
    minimumSpendCents: number;
}

export interface CartPayload {
    groups: CartGroup[];
    itemCount: number;
    totals: CartTotals;
    promo: CartPromo | null;
}

export interface CartWallet {
    availableCents: number;
    frozenCents: number;
}

export interface CartProject {
    id: number;
    name: string;
    color: string | null;
    folders: { id: number; name: string }[];
    landingPages: { id: number; folderId: number | null; anchorText: string; url: string }[];
}

export type ContentState = 'not_needed' | 'empty' | 'short' | 'ready';

export interface CheckoutContentItem {
    itemId: number;
    domain: string;
    publisherWrites: boolean;
    minWords: number;
    wordCount: number | null;
    title: string | null;
    fileName: string | null;
    /** Null when the article came from a file — the text lives on disk. */
    body: string | null;
    state: ContentState;
}

export interface CheckoutContent {
    items: CheckoutContentItem[];
    /** Lines that need an article from the buyer, done or not. */
    needed: number;
    ready: number;
}

export interface BillingDetails {
    name: string | null;
    company: string | null;
    email: string | null;
    country: string | null;
    vat_no: string | null;
    address: string | null;
}

export interface OrderSummaryPost {
    id: number;
    domain: string;
    project: string | null;
    status: string;
    statusLabel: string;
    isDraft: boolean;
    priceCents: number;
    window: string;
    deadlineAt: string | null;
}
