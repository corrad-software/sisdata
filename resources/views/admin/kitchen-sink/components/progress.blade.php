<x-admin-layout :breadcrumbs="[
    ['label' => __('Admin'), 'url' => route('admin.dashboard')],
    ['label' => __('Kitchen Sink'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Components'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Progress')],
]">
    @include('admin.kitchen-sink._tabs', ['group' => 'components'])

    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-6">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Stepper') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('A multi-step progress indicator — completed steps show a checkmark, the current step is outlined, upcoming steps are muted.') }}</p>
            </div>

            <div>
                <p class="mb-3 text-body-xs font-medium text-txt-black-500">{{ __('Step 1 — just started') }}</p>
                <x-stepper :current="1" :steps="[__('Account'), __('Profile'), __('Preferences'), __('Confirm')]" />
            </div>

            <div>
                <p class="mb-3 text-body-xs font-medium text-txt-black-500">{{ __('Step 3 — in progress') }}</p>
                <x-stepper :current="3" :steps="[__('Account'), __('Profile'), __('Preferences'), __('Confirm')]" />
            </div>

            <div>
                <p class="mb-3 text-body-xs font-medium text-txt-black-500">{{ __('All steps completed') }}</p>
                <x-stepper :current="5" :steps="[__('Account'), __('Profile'), __('Preferences'), __('Confirm')]" />
            </div>
        </section>

        <section
            class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4"
            x-data="{ step: 1, total: 4 }"
        >
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Interactive Example') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('Click through the steps — this drives the stepper live with Alpine, no page reload.') }}</p>
            </div>

            <ol class="flex items-start w-full">
                <template x-for="i in total" :key="i">
                    <li class="flex items-center" :class="i < total ? 'flex-1' : ''">
                        <div class="flex flex-col items-center gap-1.5 sm:flex-row sm:gap-2">
                            <span
                                class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-body-sm font-medium"
                                :class="i < step ? 'bg-primary-600 text-white' : (i === step ? 'border-2 border-primary-600 text-primary-600' : 'border-2 border-otl-gray-300 text-txt-black-500')"
                            >
                                <svg x-show="i < step" class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                </svg>
                                <span x-show="i >= step" x-text="i"></span>
                            </span>
                            <span class="text-body-xs sm:text-body-sm font-medium" :class="i <= step ? 'text-txt-black-900' : 'text-txt-black-500'" x-text="'{{ __('Step') }} ' + i"></span>
                        </div>
                        <div class="mx-3 flex-1" :class="i < step ? 'h-1 rounded-full bg-primary-600' : 'border-t-2 border-dashed border-otl-gray-300'" x-show="i < total"></div>
                    </li>
                </template>
            </ol>

            <div class="flex justify-between pt-2">
                <x-button variant="secondary" type="button" x-on:click="step = Math.max(1, step - 1)" x-bind:disabled="step === 1">
                    {{ __('Back') }}
                </x-button>
                <x-button variant="primary" type="button" x-on:click="step = Math.min(total, step + 1)" x-bind:disabled="step === total">
                    {{ __('Next') }}
                </x-button>
            </div>
        </section>

        <section
            class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4"
            x-data="{
                fields: { name: '', email: '', phone: '', address: '' },
                get filledCount() { return Object.values(this.fields).filter(v => v.trim() !== '').length },
                get total() { return Object.keys(this.fields).length },
                get percent() { return Math.round((this.filledCount / this.total) * 100) },
            }"
        >
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Data-Driven Progress Stepper') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('Instead of clicking Next/Back, this one measures real data as you type — fill in the fields below and watch it update live.') }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                <x-input label="{{ __('Name') }}" name="ks_progress_name" x-model="fields.name" />
                <x-input label="{{ __('Email') }}" type="email" name="ks_progress_email" x-model="fields.email" />
                <x-input label="{{ __('Phone') }}" name="ks_progress_phone" x-model="fields.phone" />
                <x-input label="{{ __('Address') }}" name="ks_progress_address" x-model="fields.address" />
            </div>

            <div class="max-w-2xl space-y-2">
                <div class="flex items-center justify-between text-body-sm">
                    <span class="font-medium text-txt-black-900" x-text="filledCount + ' {{ __('of') }} ' + total + ' {{ __('fields completed') }}'"></span>
                    <span class="text-txt-black-500" x-text="percent + '%'"></span>
                </div>

                <div class="h-2 w-full overflow-hidden rounded-full bg-bg-washed">
                    <div class="h-full rounded-full bg-primary-600 transition-all duration-300" :style="'width: ' + percent + '%'"></div>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <template x-for="(value, key) in fields" :key="key">
                        <span class="h-2.5 w-2.5 rounded-full transition-colors" :class="value.trim() !== '' ? 'bg-primary-600' : 'bg-otl-gray-300'"></span>
                    </template>
                </div>
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('History Timeline') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('A chronological activity log — the same pattern you\'d use for an audit trail or a record\'s change history.') }}</p>
            </div>

            <x-timeline :items="[
                [
                    'title' => __('User account created'),
                    'time' => __('28 Jul 2026, 9:02 AM'),
                    'description' => __('Account was created by :name.', ['name' => 'Admin']),
                    'color' => 'success',
                ],
                [
                    'title' => __('Role changed to Admin'),
                    'time' => __('28 Jul 2026, 9:15 AM'),
                    'description' => __('Updated by :name.', ['name' => 'Admin']),
                    'color' => 'primary',
                ],
                [
                    'title' => __('Password reset requested'),
                    'time' => __('28 Jul 2026, 11:40 AM'),
                    'description' => __('Requested from an unrecognised device.'),
                    'color' => 'warning',
                ],
                [
                    'title' => __('Account deactivated'),
                    'time' => __('29 Jul 2026, 4:20 PM'),
                    'description' => __('Deactivated by :name.', ['name' => 'Admin']),
                    'color' => 'danger',
                ],
            ]" />
        </section>
    </div>
</x-admin-layout>
