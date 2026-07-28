@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-otl-gray-300 focus:border-primary-600 focus:ring-fr-primary rounded-md shadow-sm text-body-sm']) }}>
