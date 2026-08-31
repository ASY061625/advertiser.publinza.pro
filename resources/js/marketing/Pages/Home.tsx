import { Head, Link } from '@inertiajs/react';
import { SiteLayout } from '../Layouts/SiteLayout';

export default function Home({ siteCount }: { siteCount: number }) {
    return (
        <SiteLayout>
            <Head title="Guest posts on vetted sites" />

            <section className="mx-auto max-w-content px-6 py-20">
                <h1 className="max-w-3xl text-3xl font-semibold text-ink-900">
                    Buy guest posts on {siteCount.toLocaleString()} vetted sites
                </h1>
                <p className="mt-5 max-w-2xl text-md text-ink-700">
                    Filter by traffic, domain rating and spam score, add placements to a cart, and track every post
                    through to publication.
                </p>
                <div className="mt-8 flex gap-3">
                    <a
                        href="https://app.publinza.pro/register"
                        className="rounded-button bg-brand px-5 py-2.5 font-sora text-md font-medium text-white hover:bg-brand-700"
                    >
                        Create an account
                    </a>
                    <Link
                        href="/how-it-works"
                        className="rounded-button border border-ink-300 px-5 py-2.5 font-sora text-md font-medium text-ink-700 hover:bg-surface-sunken"
                    >
                        See how it works
                    </Link>
                </div>
            </section>
        </SiteLayout>
    );
}
