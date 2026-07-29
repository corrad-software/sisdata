@props(['items' => []])

@php
$statusStyles = [
    'completed' => ['bg' => 'bg-bg-success-50', 'text' => 'text-txt-success', 'label' => __('Completed')],
    'in_progress' => ['bg' => 'bg-bg-warning-50', 'text' => 'text-txt-warning', 'label' => __('In Progress')],
    'not_started' => ['bg' => 'bg-bg-washed', 'text' => 'text-txt-black-500', 'label' => __('Not Started')],
];
@endphp

<ol {{ $attributes->merge(['class' => 'divide-y divide-otl-gray-200 rounded-md border border-otl-gray-200 bg-bg-white']) }}>
    @foreach ($items as $index => $item)
        @php $status = $statusStyles[$item['status'] ?? 'not_started']; @endphp
        <li class="flex items-center justify-between gap-4 px-4 py-3">
            <div class="flex items-center gap-3">
                <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-otl-gray-300 text-body-xs font-medium text-txt-black-500">
                    {{ $index + 1 }}
                </span>
                <a href="{{ $item['url'] ?? '#' }}" class="text-body-sm font-medium text-primary-600 hover:text-primary-700 hover:underline">
                    {{ $item['title'] }}
                </a>
            </div>
            <span class="inline-flex items-center rounded-full {{ $status['bg'] }} px-2.5 py-0.5 text-body-xs font-medium {{ $status['text'] }}">
                {{ $status['label'] }}
            </span>
        </li>
    @endforeach
</ol>
