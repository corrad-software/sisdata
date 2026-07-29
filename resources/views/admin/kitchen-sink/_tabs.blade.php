{{--
    Shared tab bar for both Kitchen Sink groups. Pass `group` as
    'foundation' or 'components':
        @include('admin.kitchen-sink._tabs', ['group' => 'components'])
--}}
@php
$groups = [
    'foundation' => [
        'colour' => __('Colour'),
        'typography' => __('Typography'),
        'layout-spacing' => __('Layout & Spacing'),
        'style' => __('Style & Icon'),
    ],
    'components' => [
        'buttons' => __('Buttons'),
        'forms' => __('Forms'),
        'data-display' => __('Data Display'),
        'feedback' => __('Feedback'),
        'content' => __('Content'),
        'navigation' => __('Navigation'),
        'overlays' => __('Overlays'),
        'progress' => __('Progress'),
    ],
    'data-visual' => [
        'dashboard' => __('Dashboard'),
        'stats-view' => __('Stats View'),
    ],
    'sample-pages' => [
        'login' => __('Login'),
        'register' => __('Register'),
        'not-found' => __('404 Page'),
        'empty-state' => __('Empty State'),
    ],
][$group];
@endphp

<div class="bg-bg-white border-b border-otl-divider">
    <nav class="flex flex-wrap gap-6 px-4 sm:px-6 lg:px-8 -mb-px overflow-x-auto">
        @foreach ($groups as $key => $label)
            <a
                href="{{ route('admin.kitchen-sink.' . $group . '.' . $key) }}"
                wire:navigate
                class="whitespace-nowrap border-b-2 py-3 text-body-sm font-medium {{ request()->routeIs('admin.kitchen-sink.' . $group . '.' . $key) ? 'border-primary-600 text-primary-600' : 'border-transparent text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300' }}"
            >
                {{ $label }}
            </a>
        @endforeach
    </nav>
</div>
