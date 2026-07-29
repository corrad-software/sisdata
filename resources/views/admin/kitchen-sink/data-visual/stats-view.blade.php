<x-admin-layout :breadcrumbs="[
    ['label' => __('Admin'), 'url' => route('admin.dashboard')],
    ['label' => __('Kitchen Sink'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Data Visual'), 'url' => route('admin.kitchen-sink.data-visual.dashboard')],
    ['label' => __('Stats View')],
]">
    @include('admin.kitchen-sink._tabs', ['group' => 'data-visual'])

    @php
        $topPages = [
            ['name' => '/dashboard', 'visits' => 8420],
            ['name' => '/admin/users', 'visits' => 6210],
            ['name' => '/admin/kitchen-sink', 'visits' => 4150],
            ['name' => '/profile', 'visits' => 2030],
        ];
        $maxVisits = collect($topPages)->max('visits');

        $trends = [
            ['label' => __('Page Views'), 'value' => '48,201', 'data' => [['label' => '', 'value' => 12], ['label' => '', 'value' => 19], ['label' => '', 'value' => 14], ['label' => '', 'value' => 24], ['label' => '', 'value' => 21], ['label' => '', 'value' => 30]]],
            ['label' => __('New Signups'), 'value' => '1,024', 'data' => [['label' => '', 'value' => 30], ['label' => '', 'value' => 24], ['label' => '', 'value' => 26], ['label' => '', 'value' => 18], ['label' => '', 'value' => 15], ['label' => '', 'value' => 10]]],
            ['label' => __('Bounce Rate'), 'value' => '32.4%', 'data' => [['label' => '', 'value' => 40], ['label' => '', 'value' => 38], ['label' => '', 'value' => 35], ['label' => '', 'value' => 33], ['label' => '', 'value' => 34], ['label' => '', 'value' => 32]]],
        ];

        $heatmapData = [
            ['label' => __('Mon'), 'cells' => [1, 3, 2, 4, 3, 0, 1]],
            ['label' => __('Tue'), 'cells' => [2, 4, 3, 4, 4, 1, 0]],
            ['label' => __('Wed'), 'cells' => [0, 2, 4, 3, 2, 0, 0]],
            ['label' => __('Thu'), 'cells' => [3, 4, 4, 4, 3, 1, 1]],
            ['label' => __('Fri'), 'cells' => [2, 3, 2, 3, 4, 2, 1]],
        ];
        $heatmapCols = [__('W1'), __('W2'), __('W3'), __('W4'), __('W5'), __('W6'), __('W7')];
    @endphp

    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
        <section class="space-y-4">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Highlights') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('Large centered numbers for the headline metrics that matter most.') }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-otl-gray-200 bg-bg-white p-6 text-center shadow-card">
                    <p class="font-heading text-heading-lg font-semibold text-primary-600">98.5%</p>
                    <p class="mt-1 text-body-sm text-txt-black-500">{{ __('Customer Satisfaction') }}</p>
                </div>
                <div class="rounded-lg border border-otl-gray-200 bg-bg-white p-6 text-center shadow-card">
                    <p class="font-heading text-heading-lg font-semibold text-primary-600">4.8s</p>
                    <p class="mt-1 text-body-sm text-txt-black-500">{{ __('Avg. Response Time') }}</p>
                </div>
                <div class="rounded-lg border border-otl-gray-200 bg-bg-white p-6 text-center shadow-card">
                    <p class="font-heading text-heading-lg font-semibold text-primary-600">312</p>
                    <p class="mt-1 text-body-sm text-txt-black-500">{{ __('Open Issues') }}</p>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
            <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Top Pages') }}</h3>
                    <p class="text-body-sm text-txt-black-500">{{ __('A comparison table with an inline bar showing each row\'s share of the total.') }}</p>
                </div>

                <div class="space-y-3">
                    @foreach ($topPages as $page)
                        <div class="flex items-center gap-4">
                            <p class="w-40 flex-shrink-0 truncate text-body-sm text-txt-black-900">{{ $page['name'] }}</p>
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-bg-washed">
                                <div class="h-full rounded-full bg-primary-600" style="width: {{ round($page['visits'] / $maxVisits * 100) }}%"></div>
                            </div>
                            <p class="w-16 flex-shrink-0 text-end text-body-sm text-txt-black-500">{{ number_format($page['visits']) }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Horizontal Bar Chart') }}</h3>
                    <p class="text-body-sm text-txt-black-500">{{ __('Top products by revenue — bars read left-to-right, useful for ranked lists.') }}</p>
                </div>

                <x-horizontal-bar-chart :data="[
                    ['label' => __('Product A'), 'value' => 9200],
                    ['label' => __('Product B'), 'value' => 7400],
                    ['label' => __('Product C'), 'value' => 5100],
                    ['label' => __('Product D'), 'value' => 3300],
                ]" />
            </section>
        </div>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Progress Rings') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('The donut chart component in single-value mode, with a centered percentage label.') }}</p>
            </div>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                <div class="flex flex-col items-center gap-2">
                    <x-donut-chart :size="128" :stroke-width="14" :legend="false" :rounded="true" :total="100" center-label="72%" :data="[
                        ['label' => __('Completed'), 'value' => 72, 'color' => 'text-primary-600'],
                    ]" />
                    <p class="text-body-sm text-txt-black-500">{{ __('Task Completion') }}</p>
                </div>

                <div class="flex flex-col items-center gap-2">
                    <x-donut-chart :size="128" :stroke-width="14" :legend="false" :rounded="true" :total="100" center-label="54%" :data="[
                        ['label' => __('Used'), 'value' => 54, 'color' => 'text-warning-600'],
                    ]" />
                    <p class="text-body-sm text-txt-black-500">{{ __('Storage Used') }}</p>
                </div>

                <div class="flex flex-col items-center gap-2">
                    <x-donut-chart :size="128" :stroke-width="14" :legend="false" :rounded="true" :total="100" center-label="91%" :data="[
                        ['label' => __('Progress'), 'value' => 91, 'color' => 'text-success-600'],
                    ]" />
                    <p class="text-body-sm text-txt-black-500">{{ __('Goal Progress') }}</p>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
            <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Trends') }}</h3>
                    <p class="text-body-sm text-txt-black-500">{{ __('A metric next to a mini sparkline trend — the line chart component at a smaller size.') }}</p>
                </div>

                <div class="divide-y divide-otl-gray-200">
                    @foreach ($trends as $trend)
                        <div class="flex items-center justify-between gap-6 py-3">
                            <div>
                                <p class="text-body-sm text-txt-black-500">{{ $trend['label'] }}</p>
                                <p class="font-heading text-heading-2xs font-semibold text-txt-black-900">{{ $trend['value'] }}</p>
                            </div>
                            <x-line-chart :data="$trend['data']" :height="48" :show-labels="false" class="w-32 flex-shrink-0" />
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Heatmap') }}</h3>
                    <p class="text-body-sm text-txt-black-500">{{ __('A grid of colour-intensity cells — a GitHub-style activity calendar, weekday by week.') }}</p>
                </div>

                <x-heatmap :data="$heatmapData" :col-labels="$heatmapCols" :max="4" />
            </section>
        </div>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Radar Chart') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('Multi-axis comparison — this team\'s scores against the department average.') }}</p>
            </div>

            <x-radar-chart
                :labels="[__('Speed'), __('Quality'), __('Coverage'), __('Reliability'), __('Support')]"
                :max="100"
                :series="[
                    ['name' => __('This Team'), 'color' => 'text-primary-600', 'data' => [80, 65, 90, 70, 85]],
                    ['name' => __('Department Average'), 'color' => 'text-txt-black-500', 'data' => [60, 60, 60, 60, 60]],
                ]"
            />
        </section>
    </div>
</x-admin-layout>
