<x-admin-layout :breadcrumbs="[
    ['label' => __('Admin'), 'url' => route('admin.dashboard')],
    ['label' => __('Kitchen Sink'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Components'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Overlays')],
]">
    @include('admin.kitchen-sink._tabs', ['group' => 'components'])

    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Modal') }}</h3>

            <div class="flex flex-wrap gap-3">
                <x-button variant="primary" x-data="" x-on:click="$dispatch('open-modal', 'kitchen-sink-info-modal')">
                    {{ __('Open info modal') }}
                </x-button>
                <x-button variant="danger" x-data="" x-on:click="$dispatch('open-modal', 'kitchen-sink-confirm-modal')">
                    {{ __('Open confirm modal') }}
                </x-button>
            </div>

            <x-modal name="kitchen-sink-info-modal">
                <div class="p-6">
                    <h2 class="font-heading font-semibold text-heading-2xs text-txt-black-900">
                        {{ __('Just a modal') }}
                    </h2>
                    <p class="mt-1 text-body-sm text-txt-black-500">
                        {{ __('This is the x-modal component — Alpine-driven, opened and closed via the open-modal / close-modal browser events.') }}
                    </p>
                    <div class="mt-6 flex justify-end">
                        <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Close') }}</x-button>
                    </div>
                </div>
            </x-modal>

            <x-modal name="kitchen-sink-confirm-modal">
                <div class="p-6">
                    <h2 class="font-heading font-semibold text-heading-2xs text-txt-black-900">
                        {{ __('Are you sure?') }}
                    </h2>
                    <p class="mt-1 text-body-sm text-txt-black-500">
                        {{ __('This is the same confirmation pattern used by the User Management delete flow.') }}
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <x-button variant="secondary" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
                        <x-button variant="danger" x-on:click="$dispatch('close')">{{ __('Confirm') }}</x-button>
                    </div>
                </div>
            </x-modal>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Dropdown') }}</h3>

            <x-dropdown align="left" width="48">
                <x-slot name="trigger">
                    <x-button variant="secondary">{{ __('Open dropdown') }}</x-button>
                </x-slot>
                <x-slot name="content">
                    <x-dropdown-link href="#">{{ __('Item one') }}</x-dropdown-link>
                    <x-dropdown-link href="#">{{ __('Item two') }}</x-dropdown-link>
                    <x-dropdown-link href="#">{{ __('Item three') }}</x-dropdown-link>
                </x-slot>
            </x-dropdown>
        </section>
    </div>
</x-admin-layout>
