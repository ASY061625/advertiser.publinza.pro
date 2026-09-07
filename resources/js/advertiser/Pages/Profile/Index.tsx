import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Tabs } from '@shared/ui';
import type { AdvertiserSharedProps } from '@shared/types';
import type { ProfilePageProps, ProfileTab } from '@shared/types/profile';
import { AppShell } from '../../Layouts/AppShell';
import { AccountTab } from '../../Components/profile/AccountTab';
import { ApiTab } from '../../Components/profile/ApiTab';
import { CompanyTab } from '../../Components/profile/CompanyTab';
import { NotificationsTab } from '../../Components/profile/NotificationsTab';
import { RecoveryCodes } from '../../Components/profile/RecoveryCodes';
import { SecurityTab } from '../../Components/profile/SecurityTab';

const TABS: { id: ProfileTab; label: string }[] = [
    { id: 'account', label: 'Account' },
    { id: 'company', label: 'Company' },
    { id: 'security', label: 'Security' },
    { id: 'notifications', label: 'Notifications' },
    { id: 'api', label: 'API' },
];

/**
 * The advertiser's own account.
 *
 * Five tabs over one URL, each holding its own form and saving on its own.
 * Independently, deliberately: these are five unrelated decisions, and one Save
 * that writes a password change and a timezone in the same request makes both
 * harder to reason about and one of them harder to audit.
 *
 * Every tab is mounted only while it is the visible one, so its form starts
 * from what the server just sent rather than from whatever was typed three tabs
 * ago and abandoned.
 */
export default function ProfileIndex() {
    const page = usePage<AdvertiserSharedProps & ProfilePageProps>();
    const {
        tab,
        account,
        company,
        countries,
        dateFormats,
        numberFormats,
        vatCountries,
        security,
        notifications,
        api,
        tokens,
        deletion,
    } = page.props;

    const [codes, setCodes] = useState<string[] | null>(null);

    // Flashed by whichever request generated them — confirming 2FA, or asking
    // for a fresh set. Null, not absent: the flash bag carries every key.
    const flashed = page.props.flash.recoveryCodes;

    useEffect(() => {
        if (flashed && flashed.length > 0) setCodes(flashed);
    }, [flashed]);

    return (
        <AppShell title="Profile" crumbs={[{ label: 'Profile' }]}>
            <Head title="Profile" />

            <div className="flex min-w-0 flex-col gap-5">
                <header>
                    <h1 className="font-sora text-xl font-semibold text-ink-900">Profile</h1>
                    <p className="mt-1 text-sm text-ink-500">
                        {account.displayNameFallback} · {account.email}
                    </p>
                </header>

                <Tabs
                    scrollable
                    items={TABS}
                    value={tab}
                    onChange={(next) =>
                        router.get(
                            '/profile',
                            next === 'account' ? {} : { tab: next },
                            {
                                preserveScroll: true,
                                // preserveState false on purpose: each tab
                                // should open from what the server holds, not
                                // from a half-edited form somebody left behind.
                                preserveState: false,
                                replace: true,
                            },
                        )
                    }
                />

                {tab === 'account' && (
                    <AccountTab
                        account={account}
                        countries={countries}
                        dateFormats={dateFormats}
                        numberFormats={numberFormats}
                        deletion={deletion}
                    />
                )}

                {tab === 'company' && (
                    <CompanyTab company={company} countries={countries} vatCountries={vatCountries} />
                )}

                {tab === 'security' && <SecurityTab security={security} />}

                {tab === 'notifications' && <NotificationsTab notifications={notifications} />}

                {tab === 'api' && <ApiTab api={api} tokens={tokens} />}
            </div>

            {codes !== null && <RecoveryCodes codes={codes} onDone={() => setCodes(null)} />}
        </AppShell>
    );
}
