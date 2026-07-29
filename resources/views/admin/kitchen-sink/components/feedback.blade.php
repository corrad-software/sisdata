<x-admin-layout :breadcrumbs="[
    ['label' => __('Admin'), 'url' => route('admin.dashboard')],
    ['label' => __('Kitchen Sink'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Components'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Feedback')],
]">
    @include('admin.kitchen-sink._tabs', ['group' => 'components'])

    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Alert') }}</h3>

            <div class="space-y-3 max-w-2xl">
                <x-alert type="success">This is a success alert — the action completed.</x-alert>
                <x-alert type="error">This is an error alert — something went wrong.</x-alert>
                <x-alert type="warning">This is a warning alert — proceed with caution.</x-alert>
                <x-alert type="info">This is an info alert — just for your reference.</x-alert>
                <x-alert type="info" dismissible>This one is dismissible — try the close button.</x-alert>
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Badge') }}</h3>

            <div class="flex flex-wrap gap-3">
                <x-badge variant="success">{{ __('Active') }}</x-badge>
                <x-badge variant="neutral">{{ __('Inactive') }}</x-badge>
                <x-badge variant="danger">{{ __('Error') }}</x-badge>
                <x-badge variant="warning">{{ __('Warning') }}</x-badge>
                <x-badge variant="info">{{ __('Info') }}</x-badge>
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Toast') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('Transient, auto-dismissing notifications — unlike Alert, which is a persistent inline banner. Triggerable from anywhere via a browser event: $dispatch(\'toast\', { type, message }).') }}</p>

            <div class="flex flex-wrap gap-3">
                <x-button variant="secondary" size="sm" x-on:click="$dispatch('toast', { type: 'success', message: '{{ __('Saved successfully.') }}' })">
                    {{ __('Show success toast') }}
                </x-button>
                <x-button variant="secondary" size="sm" x-on:click="$dispatch('toast', { type: 'error', message: '{{ __('Something went wrong.') }}' })">
                    {{ __('Show error toast') }}
                </x-button>
                <x-button variant="secondary" size="sm" x-on:click="$dispatch('toast', { type: 'warning', message: '{{ __('Please double-check your input.') }}' })">
                    {{ __('Show warning toast') }}
                </x-button>
                <x-button variant="secondary" size="sm" x-on:click="$dispatch('toast', { type: 'info', message: '{{ __('Heads up — just for your reference.') }}' })">
                    {{ __('Show info toast') }}
                </x-button>
            </div>

            <x-toast />
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Spinner') }}</h3>

            <div class="flex flex-wrap items-center gap-6">
                <x-spinner size="sm" class="text-primary-600" />
                <x-spinner size="md" class="text-primary-600" />
                <x-spinner size="lg" class="text-primary-600" />

                <div class="flex items-center gap-2 rounded-md border border-otl-gray-200 px-3 py-2">
                    <x-spinner size="sm" class="text-txt-black-500" />
                    <span class="text-body-sm text-txt-black-500">{{ __('Loading...') }}</span>
                </div>
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Alert Dialog') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('A confirmation-style modal — reuses x-modal with a focused, single-decision layout.') }}</p>

            <x-button variant="danger" x-data="" x-on:click="$dispatch('open-modal', 'kitchen-sink-alert-dialog')">
                {{ __('Deactivate account') }}
            </x-button>

            <x-modal name="kitchen-sink-alert-dialog" focusable>
                <div class="p-6">
                    <h2 class="font-heading font-semibold text-heading-2xs text-txt-black-900">
                        {{ __('Deactivate this account?') }}
                    </h2>
                    <p class="mt-1 text-body-sm text-txt-black-500">
                        {{ __('The user will immediately lose access. You can reactivate the account later.') }}
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
                        <x-button variant="danger" x-on:click="$dispatch('close')">{{ __('Deactivate') }}</x-button>
                    </div>
                </div>
            </x-modal>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Callout') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('A boxed message with an optional title — more prominent than Alert, used for a single important note rather than a page-level status.') }}</p>

            <div class="space-y-3 max-w-2xl">
                <x-callout type="info" title="{{ __('Did you know?') }}">
                    {{ __('You can bulk-select rows in any table to perform actions on multiple records at once.') }}
                </x-callout>
                <x-callout type="warning" title="{{ __('Before you continue') }}">
                    {{ __('This form auto-saves every 30 seconds while you type.') }}
                </x-callout>
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Phase Banner') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('A small status tag with a note, typically shown at the very top of a page to flag its maturity.') }}</p>

            <x-phase-banner phase="{{ __('Beta') }}">
                {{ __('This is a new feature. Your feedback helps us improve it.') }}
            </x-phase-banner>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Cookies Banner') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('Shown inline here for the demo (fixed="false") — in real usage it stays fixed to the bottom of the viewport. Choosing Accept or Reject remembers your choice in localStorage.') }}</p>

            <x-cookies-banner :fixed="false" />
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Tooltip') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('Hover or focus the trigger to reveal a short hint.') }}</p>

            <div class="flex flex-wrap gap-6">
                <x-tooltip text="{{ __('This action cannot be undone') }}">
                    <x-button variant="secondary" size="sm" type="button">{{ __('Hover me') }}</x-button>
                </x-tooltip>

                <x-tooltip text="{{ __('Read-only field') }}">
                    <svg class="h-5 w-5 cursor-help text-txt-black-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                </x-tooltip>
            </div>
        </section>
    </div>
</x-admin-layout>
