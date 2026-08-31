import { Head } from '@inertiajs/react';
import { AdminLayout } from '../Layouts/AdminLayout';
import { number } from '@shared/lib/format';

interface AdminDashboardProps {
    stats: { pendingSites: number; openOrders: number; payoutsDue: number };
}

export default function AdminDashboard({ stats }: AdminDashboardProps) {
    const tiles = [
        { label: 'Sites awaiting review', value: stats.pendingSites },
        { label: 'Open orders', value: stats.openOrders },
        { label: 'Payouts due', value: stats.payoutsDue },
    ];

    return (
        <AdminLayout title="Overview">
            <Head title="Overview" />

            <div className="grid grid-cols-12 gap-6">
                {tiles.map((tile) => (
                    <div key={tile.label} className="card col-span-12 p-5 md:col-span-4">
                        <p className="text-sm text-ink-500">{tile.label}</p>
                        <p className="tabular mt-2 font-sora text-xl font-semibold text-ink-900">
                            {number(tile.value)}
                        </p>
                    </div>
                ))}
            </div>
        </AdminLayout>
    );
}
