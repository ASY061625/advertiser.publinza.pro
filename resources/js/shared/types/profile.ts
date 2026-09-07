import type { DateFormatName, NumberFormatName } from '@shared/lib/format';

export type ProfileTab = 'account' | 'company' | 'security' | 'notifications' | 'api';

export interface AccountDetails {
    name: string;
    displayName: string | null;
    /** What the app actually calls them once the fallback is applied. */
    displayNameFallback: string;
    email: string;
    emailVerified: boolean;
    /** An address waiting to be confirmed from its own inbox. */
    pendingEmail: string | null;
    pendingEmailSentAt: string | null;
    phone: string | null;
    phoneCountry: string | null;
    timezone: string;
    locale: string;
    dateFormat: DateFormatName;
    numberFormat: NumberFormatName;
    avatarUrl: string | null;
    deletionRequestedAt: string | null;
}

export interface CompanyDetails {
    company: string | null;
    registrationNo: string | null;
    vatNo: string | null;
    country: string | null;
    billingAddress: string | null;
    billingEmail: string | null;
    /** The fallback when billingEmail is unset. Shown, not silently used. */
    accountEmail: string;
    logoUrl: string | null;
    /** A well-formed example for the chosen country, or null if we have no rule. */
    vatExample: string | null;
}

export interface FormatOption {
    value: string;
    label: string;
    example: string;
}

export interface TwoFactorState {
    enabled: boolean;
    /** Set up but not yet proven with a code. */
    pending: boolean;
    secret: string | null;
    provisioningUri: string | null;
    recoveryCodesLeft: number;
}

export interface ActiveSession {
    /** A hash of the session id — the id itself is a credential. */
    id: string;
    current: boolean;
    browser: string;
    platform: string;
    label: string;
    ip: string | null;
    lastActiveAt: string | null;
}

export interface LoginRow {
    id: number;
    successful: boolean;
    ip: string | null;
    country: string | null;
    countryName: string | null;
    label: string;
    at: string | null;
    /** First sign-in from a country this account had not used before. */
    newCountry: boolean;
}

export interface SecurityLogRow {
    id: number;
    action: string;
    label: string;
    changes: Record<string, unknown> | null;
    ip: string | null;
    at: string | null;
}

export interface SecurityState {
    twoFactor: TwoFactorState;
    sessions: { supported: boolean; sessions: ActiveSession[] };
    loginHistory: { rows: LoginRow[]; geoAvailable: boolean };
    log: SecurityLogRow[];
}

export interface NotificationEventRow {
    value: string;
    label: string;
    description: string;
    /** Money and orders. The email switch is on and locked. */
    transactional: boolean;
}

export type NotificationMatrix = Record<string, { email: boolean; in_app: boolean; push: boolean }>;

export interface NotificationsState {
    matrix: NotificationMatrix;
    events: NotificationEventRow[];
    channels: { value: string; label: string }[];
    pausedUntil: string | null;
}

export interface TokenAbilityOption {
    value: string;
    label: string;
    description: string;
    /** Can spend balance. Flagged in the picker. */
    destructive: boolean;
}

export interface ApiInfo {
    abilities: TokenAbilityOption[];
    docsUrl: string;
    baseUrl: string;
    rateLimit: { requests: number; window: string };
}

export interface TokenRow {
    id: number;
    name: string;
    abilities: string[];
    createdAt: string | null;
    lastUsedAt: string | null;
    expiresAt: string | null;
    expired: boolean;
}

export interface DeletionState {
    retentionDays: number;
    requestedAt: string | null;
    activePosts: number;
    frozenCents: number;
    blocked: boolean;
}

export interface ProfilePageProps {
    tab: ProfileTab;
    account: AccountDetails;
    company: CompanyDetails;
    countries: { value: string; label: string }[];
    dateFormats: FormatOption[];
    numberFormats: FormatOption[];
    vatCountries: string[];
    security: SecurityState;
    notifications: NotificationsState;
    api: ApiInfo;
    tokens: TokenRow[];
    deletion: DeletionState;
    [key: string]: unknown;
}
