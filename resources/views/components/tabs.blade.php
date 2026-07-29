@props(['tabs' => [], 'default' => null])

<div x-data="{ tab: @js($default ?? array_key_first($tabs)) }" {{ $attributes }}>
    <div class="border-b border-otl-divider">
        <nav class="flex flex-wrap gap-6 -mb-px">
            @foreach ($tabs as $key => $label)
                <button
                    type="button"
                    x-on:click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'border-primary-600 text-primary-600' : 'border-transparent text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300'"
                    class="whitespace-nowrap border-b-2 py-3 text-body-sm font-medium"
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    <div class="pt-4">
        {{ $slot }}
    </div>
</div>
