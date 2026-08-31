import { Head } from '@inertiajs/react';
import { AppLayout } from '../../Layouts/AppLayout';
import { Button } from '@shared/components/Button';
import { EmptyState } from '@shared/components/EmptyState';
import { QuantBar } from '@shared/components/QuantBar';
import { Table, Td, Th } from '@shared/components/Table';
import { money } from '@shared/lib/format';
import type { CatalogRanges, CatalogSite, Paginated } from '@shared/types';

interface CatalogIndexProps {
    sites: Paginated<CatalogSite>;
    ranges: CatalogRanges;
}

export default function CatalogIndex({ sites, ranges }: CatalogIndexProps) {
    return (
        <AppLayout title="Catalog">
            <Head title="Catalog" />

            {sites.data.length === 0 ? (
                <EmptyState
                    instruction="No sites match these filters. Widen the traffic or price range to see more."
                    action={<Button variant="secondary">Clear filters</Button>}
                />
            ) : (
                <Table density="catalog" stickyFirstColumn>
                    <thead>
                        <tr>
                            <Th>Site</Th>
                            <Th>Category</Th>
                            <Th numeric>Traffic</Th>
                            <Th numeric>DR</Th>
                            <Th numeric>DA</Th>
                            <Th numeric>Spam score</Th>
                            <Th numeric>Price</Th>
                            <Th>&nbsp;</Th>
                        </tr>
                    </thead>
                    <tbody>
                        {sites.data.map((site) => (
                            <tr key={site.id} className="hover:bg-brand-50">
                                <Td>
                                    <span className="font-medium text-ink-900">{site.domain}</span>
                                    <span className="ml-2 text-sm text-ink-500">{site.language}</span>
                                </Td>
                                <Td>{site.category}</Td>
                                <Td numeric>
                                    <QuantBar value={site.traffic} range={ranges.traffic} className="items-end" />
                                </Td>
                                <Td numeric>
                                    <QuantBar
                                        value={site.domainRating}
                                        range={ranges.domainRating}
                                        format={String}
                                        className="items-end"
                                    />
                                </Td>
                                <Td numeric>
                                    <QuantBar
                                        value={site.domainAuthority}
                                        range={ranges.domainAuthority}
                                        format={String}
                                        className="items-end"
                                    />
                                </Td>
                                <Td numeric>
                                    <QuantBar
                                        value={site.spamScore}
                                        range={ranges.spamScore}
                                        inverted
                                        format={(value) => `${value}%`}
                                        className="items-end"
                                    />
                                </Td>
                                <Td numeric>
                                    <span className="tabular font-medium text-ink-900">
                                        {money(site.priceMinorUnits)}
                                    </span>
                                </Td>
                                <Td>
                                    <Button>Add to cart</Button>
                                </Td>
                            </tr>
                        ))}
                    </tbody>
                </Table>
            )}
        </AppLayout>
    );
}
