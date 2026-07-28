@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-primary-600 text-body-sm font-medium leading-5 text-txt-black-900 focus:outline-none transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-body-sm font-medium leading-5 text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300 focus:outline-none focus:text-txt-black-900 focus:border-otl-gray-300 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
