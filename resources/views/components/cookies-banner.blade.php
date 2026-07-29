@props(['fixed' => true])

<div
    x-data="{ show: !localStorage.getItem('cookies-consent') }"
    x-show="show"
    style="display: none;"
    {{ $attributes->merge(['class' => ($fixed ? 'fixed inset-x-0 bottom-0 z-50 ' : '') . 'rounded-lg border border-otl-gray-200 bg-bg-white p-4 shadow-card']) }}
>
    <div class="mx-auto flex max-w-4xl flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-body-sm text-txt-black-700">
            {{ __('We use cookies to improve your experience on this site. By continuing to browse, you agree to our use of cookies.') }}
        </p>
        <div class="flex flex-shrink-0 gap-2">
            <x-button variant="secondary" size="sm" type="button" x-on:click="localStorage.setItem('cookies-consent', 'rejected'); show = false">
                {{ __('Reject') }}
            </x-button>
            <x-button variant="primary" size="sm" type="button" x-on:click="localStorage.setItem('cookies-consent', 'accepted'); show = false">
                {{ __('Accept') }}
            </x-button>
        </div>
    </div>
</div>
