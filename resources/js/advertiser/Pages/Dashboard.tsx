import { Head } from '@inertiajs/react';
import { AppLayout } from '../Layouts/AppLayout';
import { money, number } from '@shared/lib/format';

interface DashboardProps {
    stats: {
        activeProjects: number;
        postsInProgress: number;
        publishedThisMonth: number;
        spendThisMonthMinorUnits: number;
    };
}

export default function Dashboard({ stats }: DashboardProps) {
    const tiles = [
        { label: 'Active projects', value: number(stats.activeProjects) },
        { label: 'Posts in progress', value: number(stats.postsInProgress) },
        { label: 'Published this month', value: number(stats.publishedThisMonth) },
        { label: 'Spend this month', value: money(stats.spendThisMonthMinorUnits) },
    ];

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />

            <div className="grid grid-cols-12 gap-6">
                {tiles.map((tile) => (
                    <div key={tile.label} className="card col-span-12 p-5 md:col-span-6 xl:col-span-3">
                        <p className="text-sm text-ink-500">{tile.label}</p>
                        <p className="tabular mt-2 font-sora text-xl font-semibold text-ink-900">{tile.value}</p>
                    </div>
                ))}
            </div>
        </AppLayout>
    );
}
