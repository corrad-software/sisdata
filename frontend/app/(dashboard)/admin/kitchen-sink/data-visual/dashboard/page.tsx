import { PageContainer } from '@/components/PageContainer';
import { StatCard } from '@/components/ui/StatCard';
import { BarChart } from '@/components/ui/charts/BarChart';
import { LineChart } from '@/components/ui/charts/LineChart';
import { DonutChart } from '@/components/ui/charts/DonutChart';
import { PieChart } from '@/components/ui/charts/PieChart';
import { MultiLineChart } from '@/components/ui/charts/MultiLineChart';
import { GaugeChart } from '@/components/ui/charts/GaugeChart';
import { StackedBarChart } from '@/components/ui/charts/StackedBarChart';

const DEVICE_DATA = [
    { label: 'Jan', values: { Desktop: 220, Mobile: 140 } },
    { label: 'Feb', values: { Desktop: 260, Mobile: 180 } },
    { label: 'Mar', values: { Desktop: 240, Mobile: 210 } },
    { label: 'Apr', values: { Desktop: 300, Mobile: 260 } },
];
const DEVICE_COLORS = { Desktop: 'bg-primary-600', Mobile: 'bg-warning-500' };

const PROCESS_STEPS = ['Submit', 'Review', 'Approve', 'Complete'];

export default function DataVisualDashboardPage() {
    return (
        <PageContainer className="space-y-8">
            <section className="space-y-4">
                <div>
                    <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Key Metrics</h3>
                    <p className="text-body-sm text-txt-black-500">
                        A row of KPI cards with a trend indicator — the entry point of most dashboards.
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Total Users" value="12,842" change="+8.2%" trend="up" />
                    <StatCard label="Active Sessions" value="1,204" change="+2.4%" trend="up" />
                    <StatCard label="Support Tickets" value="37" change="-12.5%" trend="down" />
                    <StatCard label="Uptime" value="99.98%" change="Stable" trend="neutral" />
                </div>
            </section>

            <div className="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                    <div>
                        <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Bar Chart</h3>
                        <p className="text-body-sm text-txt-black-500">
                            New sign-ups per month — a plain CSS/Tailwind bar chart, no charting library required.
                        </p>
                    </div>

                    <BarChart
                        data={[
                            { label: 'Jan', value: 320 },
                            { label: 'Feb', value: 480 },
                            { label: 'Mar', value: 410 },
                            { label: 'Apr', value: 610 },
                            { label: 'May', value: 590 },
                            { label: 'Jun', value: 740 },
                        ]}
                    />
                </section>

                <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                    <div>
                        <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Line Chart</h3>
                        <p className="text-body-sm text-txt-black-500">
                            Weekly traffic trend — an SVG line chart with a filled area beneath it.
                        </p>
                    </div>

                    <LineChart
                        data={[
                            { label: 'Mon', value: 1200 },
                            { label: 'Tue', value: 1900 },
                            { label: 'Wed', value: 1500 },
                            { label: 'Thu', value: 2400 },
                            { label: 'Fri', value: 2100 },
                            { label: 'Sat', value: 1700 },
                            { label: 'Sun', value: 1300 },
                        ]}
                    />
                </section>
            </div>

            <div className="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                    <div>
                        <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Donut Chart</h3>
                        <p className="text-body-sm text-txt-black-500">
                            Users by role — an SVG donut chart with a matching legend.
                        </p>
                    </div>

                    <DonutChart
                        data={[
                            { label: 'User', value: 68, color: 'text-primary-600' },
                            { label: 'Admin', value: 12, color: 'text-success-600' },
                            { label: 'Editor', value: 15, color: 'text-warning-600' },
                            { label: 'Guest', value: 5, color: 'text-danger-600' },
                        ]}
                    />
                </section>

                <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                    <div>
                        <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Pie Chart</h3>
                        <p className="text-body-sm text-txt-black-500">
                            Traffic by channel — a filled-center variant of the donut chart, drawn with true SVG
                            wedges.
                        </p>
                    </div>

                    <PieChart
                        data={[
                            { label: 'Organic Search', value: 42, color: 'text-primary-600' },
                            { label: 'Direct', value: 27, color: 'text-success-600' },
                            { label: 'Social', value: 18, color: 'text-warning-600' },
                            { label: 'Referral', value: 13, color: 'text-danger-600' },
                        ]}
                    />
                </section>
            </div>

            <div className="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                    <div>
                        <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                            Multi-Series Line Chart
                        </h3>
                        <p className="text-body-sm text-txt-black-500">
                            Compare more than one trend on the same chart — this year against last year.
                        </p>
                    </div>

                    <MultiLineChart
                        labels={['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun']}
                        series={[
                            { name: 'This Year', color: 'text-primary-600', data: [320, 480, 410, 610, 590, 740] },
                            { name: 'Last Year', color: 'text-txt-black-500', data: [280, 360, 390, 420, 460, 520] },
                        ]}
                    />
                </section>

                <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                    <div>
                        <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Gauge Chart</h3>
                        <p className="text-body-sm text-txt-black-500">
                            A single metric shown against a min/max range, speedometer-style.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-6">
                        <GaugeChart size={150} value={72} min={0} max={100} color="text-primary-600" label="CPU Usage" />
                        <GaugeChart size={150} value={41} min={0} max={100} color="text-success-600" label="Memory Usage" />
                        <GaugeChart size={150} value={88} min={0} max={100} color="text-danger-600" label="Disk Usage" />
                    </div>
                </section>
            </div>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-6">
                <div>
                    <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                        Stacked / Grouped Bar Chart
                    </h3>
                    <p className="text-body-sm text-txt-black-500">
                        The same two-series data shown stacked, then grouped side-by-side.
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div>
                        <p className="mb-3 text-body-xs font-medium text-txt-black-500">Stacked</p>
                        <StackedBarChart data={DEVICE_DATA} colors={DEVICE_COLORS} mode="stacked" />
                    </div>

                    <div>
                        <p className="mb-3 text-body-xs font-medium text-txt-black-500">Grouped</p>
                        <StackedBarChart data={DEVICE_DATA} colors={DEVICE_COLORS} mode="grouped" />
                    </div>
                </div>
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <div>
                    <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                        Process Flow Diagram
                    </h3>
                    <p className="text-body-sm text-txt-black-500">
                        A simple connected-step diagram — useful for illustrating a workflow or approval process.
                    </p>
                </div>

                <div className="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-0 overflow-x-auto">
                    {PROCESS_STEPS.map((step, index) => (
                        <div key={step} className="flex flex-1 items-center gap-0">
                            <div className="flex flex-1 flex-col items-center gap-2 rounded-lg border border-otl-gray-200 bg-bg-washed px-4 py-4 text-center">
                                <span className="flex h-8 w-8 items-center justify-center rounded-full bg-primary-600 text-body-sm font-medium text-white">
                                    {index + 1}
                                </span>
                                <p className="text-body-sm font-medium text-txt-black-900">{step}</p>
                            </div>

                            {index < PROCESS_STEPS.length - 1 && (
                                <svg
                                    className="hidden h-5 w-5 flex-shrink-0 text-txt-black-500 sm:block"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    strokeWidth={2}
                                >
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            )}
                        </div>
                    ))}
                </div>
            </section>
        </PageContainer>
    );
}
