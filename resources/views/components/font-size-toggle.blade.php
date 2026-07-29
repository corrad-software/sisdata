@php
$sizes = [
    'normal' => __('Default'),
    'large' => __('Large'),
    'xlarge' => __('Extra Large'),
];
@endphp

<div
    x-data="{
        size: localStorage.getItem('fontSize') || 'normal',
        setSize(s) {
            this.size = s;
            localStorage.setItem('fontSize', s);
            document.documentElement.classList.remove('font-size-large', 'font-size-xlarge');
            if (s !== 'normal') document.documentElement.classList.add('font-size-' + s);
        },
    }"
    x-on:livewire:navigated.window="size = localStorage.getItem('fontSize') || 'normal'"
    {{ $attributes }}
>
    <x-dropdown align="right" width="48" content-classes="bg-bg-white">
        <x-slot name="trigger">
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-md p-2 text-txt-black-500 hover:text-txt-black-900 hover:bg-bg-washed focus:outline-none transition ease-in-out duration-150"
                aria-label="{{ __('Text size') }}"
            >
                <span class="flex h-5 w-5 items-center justify-center text-sm font-bold leading-none">Aa</span>
            </button>
        </x-slot>

        <x-slot name="content">
            @foreach ($sizes as $value => $label)
                <button
                    type="button"
                    x-on:click="setSize('{{ $value }}')"
                    class="flex w-full items-center justify-between px-4 py-2 text-start text-body-sm leading-5 text-txt-black-700 hover:bg-bg-washed focus:outline-none focus:bg-bg-washed transition duration-150 ease-in-out"
                    :aria-current="size === '{{ $value }}'"
                >
                    <span>{{ $label }}</span>

                    <svg x-show="size === '{{ $value }}'" style="display: none" class="h-4 w-4 text-primary-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                    </svg>
                </button>
            @endforeach
        </x-slot>
    </x-dropdown>
</div>
