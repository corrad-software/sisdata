@props(['items' => []])

<dl {{ $attributes->merge(['class' => 'divide-y divide-otl-gray-200 rounded-md border border-otl-gray-200 bg-bg-white']) }}>
    @foreach ($items as $item)
        <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:gap-4">
            <dt class="text-body-sm font-medium text-txt-black-500 sm:w-40 sm:flex-shrink-0">{{ $item['label'] }}</dt>
            <dd class="flex-1 text-body-sm text-txt-black-900">{{ $item['value'] }}</dd>
            @if (!empty($item['action']))
                <dd>
                    <a href="{{ $item['action']['url'] ?? '#' }}" class="text-body-sm font-medium text-primary-600 hover:text-primary-700">
                        {{ $item['action']['label'] ?? __('Change') }}
                    </a>
                </dd>
            @endif
        </div>
    @endforeach
</dl>
