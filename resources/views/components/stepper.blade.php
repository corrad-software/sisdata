@props(['steps' => [], 'current' => 1])

<ol {{ $attributes->merge(['class' => 'flex items-start w-full']) }}>
    @foreach ($steps as $index => $label)
        @php $stepNumber = $index + 1; @endphp

        <li class="flex items-center {{ ! $loop->last ? 'flex-1' : '' }}">
            <div class="flex flex-col items-center gap-1.5 sm:flex-row sm:gap-2">
                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-body-sm font-medium {{ $stepNumber < $current ? 'bg-primary-600 text-white' : ($stepNumber === $current ? 'border-2 border-primary-600 text-primary-600' : 'border-2 border-otl-gray-300 text-txt-black-500') }}">
                    @if ($stepNumber < $current)
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                        </svg>
                    @else
                        {{ $stepNumber }}
                    @endif
                </span>

                <span class="text-body-xs text-center sm:text-body-sm sm:text-left font-medium {{ $stepNumber <= $current ? 'text-txt-black-900' : 'text-txt-black-500' }}">
                    {{ $label }}
                </span>
            </div>

            @if (! $loop->last)
                <div class="mx-3 flex-1 {{ $stepNumber < $current ? 'h-1 rounded-full bg-primary-600' : 'border-t-2 border-dashed border-otl-gray-300' }}"></div>
            @endif
        </li>
    @endforeach
</ol>
