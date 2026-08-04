import { LineChart } from '@/components/ui/charts/LineChart';
import { DonutChart } from '@/components/ui/charts/DonutChart';
import { HorizontalBarChart } from '@/components/ui/charts/HorizontalBarChart';
import { Heatmap } from '@/components/ui/charts/Heatmap';
import { RadarChart } from '@/components/ui/charts/RadarChart';

const TOP_PAGES = [
    { name: '/dashboard', visits: 8420 },
    { name: '/admin/users', visits: 6210 },
    { name: '/admin/kitchen-sink', visits: 4150 },
    { name: '/profile', visits: 2030 },
];
const MAX_VISITS = Math.max(...TOP_PAGES.map((p) => p.visits));

const TRENDS = [
    {
        label: 'Page Views',
        value: '48,201',
        data: [12, 19, 14, 24, 21, 30].map((value) => ({ label: '', value })),
    },
    {
        label: 'New Signups',
        value: '1,024',
        data: [30, 24, 26, 18, 15, 10].map((value) => ({ label: '', value })),
    },
    {
        label: 'Bounce Rate',
        value: '32.4%',
        data: [40, 38, 35, 33, 34, 32].map((value) => ({ label: '', value })),
    },
];

const HEATMAP_DATA = [
    { label: 'Mon', cells: [1, 3, 2, 4, 3, 0, 1] },
    { label: 'Tue', cells: [2, 4, 3, 4, 4, 1, 0] },
    { label: 'Wed', cells: [0, 2, 4, 3, 2, 0, 0] },
    { label: 'Thu', cells: [3, 4, 4, 4, 3, 1, 1] },
    { label: 'Fri', cells: [2, 3, 2, 3, 4, 2, 1] },
];
const HEATMAP_COLS = ['W1', 'W2', 'W3', 'W4', 'W5', 'W6', 'W7'];

export default function StatsViewPage() {
    return (
        <div className="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
            <section className="space-y-4">
                <div>
                    <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Highlights</h3>
                    <p className="text-body-sm text-txt-black-500">
                        Large centered numbers for the headline metrics that matter most.
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div className="rounded-lg border border-otl-gray-200 bg-bg-white p-6 text-center shadow-card">
                        <p className="font-heading text-heading-lg font-semibold text-primary-600">98.5%</p>
                        <p className="mt-1 text-body-sm text-txt-black-500">Customer Satisfaction</p>
                    </div>
                    <div className="rounded-lg border border-otl-gray-200 bg-bg-white p-6 text-center shadow-card">
                        <p className="font-heading text-heading-lg font-semibold text-primary-600">4.8s</p>
                        <p className="mt-1 text-body-sm text-txt-black-500">Avg. Response Time</p>
                    </div>
                    <div className="rounded-lg border border-otl-gray-200 bg-bg-white p-6 text-center shadow-card">
                        <p className="font-heading text-heading-lg font-semibold text-primary-600">312</p>
                        <p className="mt-1 text-body-sm text-txt-black-500">Open Issues</p>
                    </div>
                </div>
            </section>

            <div className="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                    <div>
                        <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Top Pages</h3>
                        <p className="text-body-sm text-txt-black-500">
                            A comparison table with an inline bar showing each row&apos;s share of the total.
                        </p>
                    </div>

                    <div className="space-y-3">
                        {TOP_PAGES.map((page) => (
                            <div key={page.name} className="flex items-center gap-4">
                                <p className="w-40 flex-shrink-0 truncate text-body-sm text-txt-black-900">{page.name}</p>
                                <div className="h-2 flex-1 overflow-hidden rounded-full bg-bg-washed">
                                    <div
                                        className="h-full rounded-full bg-primary-600"
                                        style={{ width: `${Math.round((page.visits / MAX_VISITS) * 100)}%` }}
                                    />
                                </div>
                                <p className="w-16 flex-shrink-0 text-end text-body-sm text-txt-black-500">
                                    {page.visits.toLocaleString()}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                    <div>
                        <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                            Horizontal Bar Chart
                        </h3>
                        <p className="text-body-sm text-txt-black-500">
                            Top products by revenue — bars read left-to-right, useful for ranked lists.
                        </p>
                    </div>

                    <HorizontalBarChart
                        data={[
                            { label: 'Product A', value: 9200 },
                            { label: 'Product B', value: 7400 },
                            { label: 'Product C', value: 5100 },
                            { label: 'Product D', value: 3300 },
                        ]}
                    />
                </section>
            </div>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <div>
                    <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Progress Rings</h3>
                    <p className="text-body-sm text-txt-black-500">
                        The donut chart component in single-value mode, with a centered percentage label.
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div className="flex flex-col items-center gap-2">
                        <DonutChart
                            size={128}
                            strokeWidth={14}
                            legend={false}
                            rounded
                            total={100}
                            centerLabel="72%"
                            data={[{ label: 'Completed', value: 72, color: 'text-primary-600' }]}
                        />
                        <p className="text-body-sm text-txt-black-500">Task Completion</p>
                    </div>

                    <div className="flex flex-col items-center gap-2">
                        <DonutChart
                            size={128}
                            strokeWidth={14}
                            legend={false}
                            rounded
                            total={100}
                            centerLabel="54%"
                            data={[{ label: 'Used', value: 54, color: 'text-warning-600' }]}
                        />
                        <p className="text-body-sm text-txt-black-500">Storage Used</p>
                    </div>

                    <div className="flex flex-col items-center gap-2">
                        <DonutChart
                            size={128}
                            strokeWidth={14}
                            legend={false}
                            rounded
                            total={100}
                            centerLabel="91%"
                            data={[{ label: 'Progress', value: 91, color: 'text-success-600' }]}
                        />
                        <p className="text-body-sm text-txt-black-500">Goal Progress</p>
                    </div>
                </div>
            </section>

            <div className="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                    <div>
                        <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Trends</h3>
                        <p className="text-body-sm text-txt-black-500">
                            A metric next to a mini sparkline trend — the line chart component at a smaller size.
                        </p>
                    </div>

                    <div className="divide-y divide-otl-gray-200">
                        {TRENDS.map((trend) => (
                            <div key={trend.label} className="flex items-center justify-between gap-6 py-3">
                                <div>
                                    <p className="text-body-sm text-txt-black-500">{trend.label}</p>
                                    <p className="font-heading text-heading-2xs font-semibold text-txt-black-900">{trend.value}</p>
                                </div>
                                <LineChart data={trend.data} height={48} showLabels={false} className="w-32 flex-shrink-0" />
                            </div>
                        ))}
                    </div>
                </section>

                <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                    <div>
                        <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Heatmap</h3>
                        <p className="text-body-sm text-txt-black-500">
                            A grid of colour-intensity cells — a GitHub-style activity calendar, weekday by week.
                        </p>
                    </div>

                    <Heatmap data={HEATMAP_DATA} colLabels={HEATMAP_COLS} max={4} />
                </section>
            </div>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <div>
                    <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Radar Chart</h3>
                    <p className="text-body-sm text-txt-black-500">
                        Multi-axis comparison — this team&apos;s scores against the department average.
                    </p>
                </div>

                <RadarChart
                    labels={['Speed', 'Quality', 'Coverage', 'Reliability', 'Support']}
                    max={100}
                    series={[
                        { name: 'This Team', color: 'text-primary-600', data: [80, 65, 90, 70, 85] },
                        { name: 'Department Average', color: 'text-txt-black-500', data: [60, 60, 60, 60, 60] },
                    ]}
                />
            </section>
        </div>
    );
}
