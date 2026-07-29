@props(['items' => [], 'home' => null])

<nav aria-label="{{ __('Breadcrumb') }}" {{ $attributes->merge(['class' => 'flex']) }}>
    <ol class="flex flex-wrap items-center gap-1.5 text-body-xs">
        <li>
            <a href="{{ $home ?? route('dashboard') }}" wire:navigate class="text-txt-black-500 hover:text-txt-black-900">
                {{ __('Home') }}
            </a>
        </li>

        @foreach ($items as $item)
            <li class="flex items-center gap-1.5">
                <svg class="h-3.5 w-3.5 text-txt-black-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>

                @if (! empty($item['url']) && ! $loop->last)
                    <a href="{{ $item['url'] }}" wire:navigate class="text-txt-black-500 hover:text-txt-black-900">
                        {{ $item['label'] }}
                    </a>
                @else
                    <span class="font-medium text-txt-black-900" aria-current="page">
                        {{ $item['label'] }}
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
