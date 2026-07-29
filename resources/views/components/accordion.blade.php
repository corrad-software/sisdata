@props(['items' => []])

<div
    x-data="{ openIndex: null, toggle(i) { this.openIndex = this.openIndex === i ? null : i } }"
    {{ $attributes->merge(['class' => 'divide-y divide-otl-gray-200 rounded-md border border-otl-gray-200 bg-bg-white']) }}
>
    @foreach ($items as $index => $item)
        <div>
            <button
                type="button"
                x-on:click="toggle({{ $index }})"
                x-bind:aria-expanded="openIndex === {{ $index }}"
                class="flex w-full items-center justify-between gap-3 px-4 py-3 text-start text-body-sm font-medium text-txt-black-900 hover:bg-bg-washed"
            >
                <span>{{ $item['title'] }}</span>
                <svg class="h-4 w-4 flex-shrink-0 transition-transform" :class="openIndex === {{ $index }} ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>
            <div
                x-show="openIndex === {{ $index }}"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-1"
                class="px-4 pb-3 text-body-sm text-txt-black-500"
            >
                {{ $item['content'] }}
            </div>
        </div>
    @endforeach
</div>
