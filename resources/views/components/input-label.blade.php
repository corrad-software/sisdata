@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-body-sm text-txt-black-900']) }}>
    {{ $value ?? $slot }}
</label>
