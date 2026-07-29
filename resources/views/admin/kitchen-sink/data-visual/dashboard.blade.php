<x-admin-layout :breadcrumbs="[
    ['label' => __('Admin'), 'url' => route('admin.dashboard')],
    ['label' => __('Kitchen Sink'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Data Visual'), 'url' => route('admin.kitchen-sink.data-visual.dashboard')],
    ['label' => __('Dashboard')],
]">
    @include('admin.kitchen-sink._tabs', ['group' => 'data-visual'])

    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
        <section class="space-y-4">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Key Metrics') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('A row of KPI cards with a trend indicator — the entry point of most dashboards.') }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-stat-card label="{{ __('Total Users') }}" value="12,842" change="+8.2%" trend="up" />
                <x-stat-card label="{{ __('Active Sessions') }}" value="1,204" change="+2.4%" trend="up" />
                <x-stat-card label="{{ __('Support Tickets') }}" value="37" change="-12.5%" trend="down" />
                <x-stat-card label="{{ __('Uptime') }}" value="99.98%" change="{{ __('Stable') }}" trend="neutral" />
            </div>
        </section>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
            <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Bar Chart') }}</h3>
                    <p class="text-body-sm text-txt-black-500">{{ __('New sign-ups per month — a plain CSS/Tailwind bar chart, no charting library required.') }}</p>
                </div>

                <x-bar-chart :data="[
                    ['label' => __('Jan'), 'value' => 320],
                    ['label' => __('Feb'), 'value' => 480],
                    ['label' => __('Mar'), 'value' => 410],
                    ['label' => __('Apr'), 'value' => 610],
                    ['label' => __('May'), 'value' => 590],
                    ['label' => __('Jun'), 'value' => 740],
                ]" />
            </section>

            <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Line Chart') }}</h3>
                    <p class="text-body-sm text-txt-black-500">{{ __('Weekly traffic trend — an SVG line chart with a filled area beneath it.') }}</p>
                </div>

                <x-line-chart :data="[
                    ['label' => __('Mon'), 'value' => 1200],
                    ['label' => __('Tue'), 'value' => 1900],
                    ['label' => __('Wed'), 'value' => 1500],
                    ['label' => __('Thu'), 'value' => 2400],
                    ['label' => __('Fri'), 'value' => 2100],
                    ['label' => __('Sat'), 'value' => 1700],
                    ['label' => __('Sun'), 'value' => 1300],
                ]" />
            </section>
        </div>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
            <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Donut Chart') }}</h3>
                    <p class="text-body-sm text-txt-black-500">{{ __('Users by role — an SVG donut chart with a matching legend.') }}</p>
                </div>

                <x-donut-chart :data="[
                    ['label' => __('User'), 'value' => 68, 'color' => 'text-primary-600'],
                    ['label' => __('Admin'), 'value' => 12, 'color' => 'text-success-600'],
                    ['label' => __('Editor'), 'value' => 15, 'color' => 'text-warning-600'],
                    ['label' => __('Guest'), 'value' => 5, 'color' => 'text-danger-600'],
                ]" />
            </section>

            <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Pie Chart') }}</h3>
                    <p class="text-body-sm text-txt-black-500">{{ __('Traffic by channel — a filled-center variant of the donut chart, drawn with true SVG wedges.') }}</p>
                </div>

                <x-pie-chart :data="[
                    ['label' => __('Organic Search'), 'value' => 42, 'color' => 'text-primary-600'],
                    ['label' => __('Direct'), 'value' => 27, 'color' => 'text-success-600'],
                    ['label' => __('Social'), 'value' => 18, 'color' => 'text-warning-600'],
                    ['label' => __('Referral'), 'value' => 13, 'color' => 'text-danger-600'],
                ]" />
            </section>
        </div>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
            <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Multi-Series Line Chart') }}</h3>
                    <p class="text-body-sm text-txt-black-500">{{ __('Compare more than one trend on the same chart — this year against last year.') }}</p>
                </div>

                <x-multi-line-chart
                    :labels="[__('Jan'), __('Feb'), __('Mar'), __('Apr'), __('May'), __('Jun')]"
                    :series="[
                        ['name' => __('This Year'), 'color' => 'text-primary-600', 'data' => [320, 480, 410, 610, 590, 740]],
                        ['name' => __('Last Year'), 'color' => 'text-txt-black-500', 'data' => [280, 360, 390, 420, 460, 520]],
                    ]"
                />
            </section>

            <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Gauge Chart') }}</h3>
                    <p class="text-body-sm text-txt-black-500">{{ __('A single metric shown against a min/max range, speedometer-style.') }}</p>
                </div>

                <div class="flex flex-wrap gap-6">
                    <x-gauge-chart :size="150" :value="72" :min="0" :max="100" color="text-primary-600" label="{{ __('CPU Usage') }}" />
                    <x-gauge-chart :size="150" :value="41" :min="0" :max="100" color="text-success-600" label="{{ __('Memory Usage') }}" />
                    <x-gauge-chart :size="150" :value="88" :min="0" :max="100" color="text-danger-600" label="{{ __('Disk Usage') }}" />
                </div>
            </section>
        </div>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-6">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Stacked / Grouped Bar Chart') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('The same two-series data shown stacked, then grouped side-by-side.') }}</p>
            </div>

            @php
                $ksDeviceData = [
                    ['label' => __('Jan'), 'values' => ['Desktop' => 220, 'Mobile' => 140]],
                    ['label' => __('Feb'), 'values' => ['Desktop' => 260, 'Mobile' => 180]],
                    ['label' => __('Mar'), 'values' => ['Desktop' => 240, 'Mobile' => 210]],
                    ['label' => __('Apr'), 'values' => ['Desktop' => 300, 'Mobile' => 260]],
                ];
                $ksDeviceColors = ['Desktop' => 'bg-primary-600', 'Mobile' => 'bg-warning-500'];
            @endphp

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div>
                    <p class="mb-3 text-body-xs font-medium text-txt-black-500">{{ __('Stacked') }}</p>
                    <x-stacked-bar-chart :data="$ksDeviceData" :colors="$ksDeviceColors" mode="stacked" />
                </div>

                <div>
                    <p class="mb-3 text-body-xs font-medium text-txt-black-500">{{ __('Grouped') }}</p>
                    <x-stacked-bar-chart :data="$ksDeviceData" :colors="$ksDeviceColors" mode="grouped" />
                </div>
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Process Flow Diagram') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('A simple connected-step diagram — useful for illustrating a workflow or approval process.') }}</p>
            </div>

            <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-0 overflow-x-auto">
                @foreach ([__('Submit'), __('Review'), __('Approve'), __('Complete')] as $index => $step)
                    <div class="flex flex-1 items-center gap-0">
                        <div class="flex flex-1 flex-col items-center gap-2 rounded-lg border border-otl-gray-200 bg-bg-washed px-4 py-4 text-center">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-600 text-body-sm font-medium text-white">
                                {{ $index + 1 }}
                            </span>
                            <p class="text-body-sm font-medium text-txt-black-900">{{ $step }}</p>
                        </div>

                        @if (!$loop->last)
                            <svg class="hidden h-5 w-5 flex-shrink-0 text-txt-black-500 sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-admin-layout>
