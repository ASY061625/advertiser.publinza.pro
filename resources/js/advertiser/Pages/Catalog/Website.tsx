import { Head, Link } from '@inertiajs/react';
import { AppShell } from '../../Layouts/AppShell';
import { ChevronLeftIcon } from '@shared/ui';
import type { BuyingConfig, CatalogProject, CatalogRangeSet, CatalogSiteDetail } from '@shared/types/catalog';
import { BuyBar } from '../../Components/catalog/website/BuyBar';
import { SiteHeader } from '../../Components/catalog/website/SiteHeader';
import { WebsiteDetail } from '../../Components/catalog/website/WebsiteDetail';

interface Props {
    site: CatalogSiteDetail;
    ranges: CatalogRangeSet;
    buying: BuyingConfig;
    project: CatalogProject | null;
}

/**
 * One website, as a page.
 *
 * The same URL the drawer fetches, rendered for a browser instead of for a
 * panel — which is what makes the drawer deep-linkable without a second
 * implementation of it. Somebody pasting this link into Slack gets a real page
 * with a title and a back link; somebody clicking a row gets the same content
 * without losing the result set behind it.
 *
 * The buy bar is sticky here for the same reason it is pinned in the drawer:
 * the body is nine metric tiles and eight terms tall, and a price you have to
 * scroll back up for is a price you decide without.
 */
export default function CatalogWebsite({ site, ranges, buying, project }: Props) {
    const back = project === null ? '/catalog' : `/catalog?project=${project.id}`;

    return (
        <AppShell
            title={site.domain}
            crumbs={[{ label: 'Catalog of websites', href: back }, { label: site.domain }]}
        >
            <Head title={`${site.domain} — Publinza catalog`} />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 pb-24">
                <Link
                    href={back}
                    className="inline-flex items-center gap-1 self-start text-sm text-ink-500 hover:text-ink-700"
                >
                    <ChevronLeftIcon size={14} />
                    {project === null ? 'Back to the catalog' : `Back to ${project.name}’s catalog`}
                </Link>

                <div className="flex rounded-card border border-subtle bg-card p-5 shadow-card">
                    <SiteHeader site={site} heading="h1" />
                </div>

                <WebsiteDetail site={site} ranges={ranges} />
            </div>

            <div className="sticky bottom-0 z-10 -mx-6 mt-6 flex border-t border-subtle bg-card px-6 py-3 shadow-card">
                <div className="mx-auto flex w-full max-w-3xl">
                    <BuyBar site={site} projectId={project?.id ?? null} buying={buying} />
                </div>
            </div>
        </AppShell>
    );
}
