import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Button, Input, Select, Switch } from '@shared/ui';
import { money } from '@shared/lib/format';
import type { BuyingConfig, CatalogSiteDetail } from '@shared/types/catalog';

interface Props {
    site: CatalogSiteDetail;
    projectId: number;
    buying: BuyingConfig;
    onDone: () => void;
}

const MANUAL = 'manual';

/**
 * The order, configured before it reaches the cart.
 *
 * Five choices, and every one of them changes either the price or what the
 * publisher receives. Adding to the cart without them would mean the cart holds
 * an order nobody has specified — and the cart is where the money is frozen, so
 * "I will fill that in later" is the wrong side of the line.
 *
 * The total is recomputed on every change and shown in the footer, because the
 * two fees are optional and a buyer choosing between them is choosing between
 * prices.
 */
export function BuyPopover({ site, projectId, buying, onDone }: Props) {
    const [serviceType, setServiceType] = useState(site.services[0]?.type ?? 'article_placement');
    const [publisherWrites, setPublisherWrites] = useState(false);
    const [express, setExpress] = useState(false);
    const [folderId, setFolderId] = useState<string>('');
    const [landingPageId, setLandingPageId] = useState<string>(
        buying.landingPages[0] ? String(buying.landingPages[0].id) : MANUAL,
    );
    const [anchorText, setAnchorText] = useState('');
    const [targetUrl, setTargetUrl] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const service = site.services.find((entry) => entry.type === serviceType) ?? site.services[0];

    // Folders narrow the landing pages, because a landing page belongs to one.
    // Choosing a folder and then being offered another folder's URLs is how an
    // order ends up pointing somewhere nobody meant.
    const landingPages = useMemo(
        () =>
            folderId === ''
                ? buying.landingPages
                : buying.landingPages.filter((page) => String(page.folderId ?? '') === folderId),
        [buying.landingPages, folderId],
    );

    const manual = landingPageId === MANUAL || landingPages.length === 0;
    const chosen = buying.landingPages.find((page) => String(page.id) === landingPageId);

    const total = useMemo(() => {
        if (!service) return 0;

        return (
            service.priceCents +
            (publisherWrites ? service.writingFeeCents : 0) +
            (express ? service.expressFeeCents : 0)
        );
    }, [express, publisherWrites, service]);

    function submit() {
        const anchor = manual ? anchorText.trim() : (chosen?.anchorText ?? '');
        const url = manual ? targetUrl.trim() : (chosen?.url ?? '');

        if (anchor === '' || url === '') {
            setError('Pick a landing page, or type an anchor and a URL.');

            return;
        }

        setSaving(true);
        setError(null);

        router.post(
            `/cart/${site.slug}`,
            {
                service_type: serviceType,
                content_mode: publisherWrites ? 'publisher_writes' : 'advertiser_provides',
                project_id: projectId,
                folder_id: folderId === '' ? null : Number(folderId),
                anchor_text: anchor,
                target_url: url,
                express,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: onDone,
                onError: (errors) => setError(Object.values(errors)[0] ?? 'That could not be added.'),
                onFinish: () => setSaving(false),
            },
        );
    }

    return (
        <div className="flex w-[340px] flex-col gap-3 p-4">
            <p className="font-sora text-sm font-semibold text-ink-900">Configure this placement</p>

            <Select
                label="Service"
                value={serviceType}
                onChange={(event) => setServiceType(event.target.value)}
                options={site.services.map((entry) => ({ value: entry.type, label: entry.label }))}
            />

            <fieldset className="flex flex-col gap-2">
                <legend className="mb-1 text-sm font-medium text-ink-700">Content</legend>

                {(
                    [
                        [false, 'I provide the article', 0],
                        [true, 'The publisher writes it', service?.writingFeeCents ?? 0],
                    ] as const
                ).map(([value, label, fee]) => (
                    <label key={label} className="flex cursor-pointer items-center gap-2 text-base text-ink-700">
                        <input
                            type="radio"
                            name="content-mode"
                            checked={publisherWrites === value}
                            onChange={() => setPublisherWrites(value)}
                            className="size-4 accent-[color:var(--brand-blue)]"
                        />
                        <span>{label}</span>
                        {/* The fee sits on the option that incurs it, not in a
                            footnote: this is the moment the choice is made. */}
                        {fee > 0 && <span className="num ml-auto text-sm text-ink-500">+{money(fee)}</span>}
                    </label>
                ))}
            </fieldset>

            {buying.folders.length > 0 && (
                <Select
                    label="Folder"
                    value={folderId}
                    onChange={(event) => {
                        setFolderId(event.target.value);
                        setLandingPageId(MANUAL);
                    }}
                    options={[
                        { value: '', label: 'No folder' },
                        ...buying.folders.map((folder) => ({ value: String(folder.id), label: folder.name })),
                    ]}
                />
            )}

            <Select
                label="Landing page"
                value={landingPageId}
                onChange={(event) => setLandingPageId(event.target.value)}
                options={[
                    ...landingPages.map((page) => ({ value: String(page.id), label: page.anchorText })),
                    { value: MANUAL, label: 'Enter one manually…' },
                ]}
                hint={manual ? undefined : chosen?.url}
            />

            {manual && (
                <>
                    <Input
                        label="Anchor text"
                        value={anchorText}
                        onChange={(event) => setAnchorText(event.target.value)}
                    />
                    <Input
                        label="Target URL"
                        type="url"
                        placeholder="https://"
                        value={targetUrl}
                        onChange={(event) => setTargetUrl(event.target.value)}
                    />
                </>
            )}

            {(service?.expressFeeCents ?? 0) > 0 && (
                <Switch
                    label="Express delivery"
                    hint={`+${money(service?.expressFeeCents ?? 0)}`}
                    checked={express}
                    onCheckedChange={setExpress}
                />
            )}

            {error && (
                <p role="alert" className="text-sm text-danger">
                    {error}
                </p>
            )}

            <div className="flex items-center justify-between gap-3 border-t border-subtle pt-3">
                <span className="num text-md font-semibold text-ink-900">{money(total)}</span>

                <Button onClick={submit} loading={saving}>
                    Add to cart
                </Button>
            </div>
        </div>
    );
}
