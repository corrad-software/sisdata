<x-admin-layout :breadcrumbs="[
    ['label' => __('Admin'), 'url' => route('admin.dashboard')],
    ['label' => __('Kitchen Sink'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Components'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Forms')],
]">
    @include('admin.kitchen-sink._tabs', ['group' => 'components'])

    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Form Input') }}</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                <x-input label="Name" name="ks_name" placeholder="Jane Doe" />
                <x-input label="Email" type="email" name="ks_email" required placeholder="jane@example.com" />
                <x-input label="Password" type="password" name="ks_password" help="Must be at least 8 characters." />
                <x-input label="Email (with error)" name="ks_email_error" value="not-an-email" error="Please enter a valid email address." />
                <x-input label="Disabled" name="ks_disabled" value="Can't touch this" disabled />
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Password Input') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('A show/hide toggle built on top of the plain input — no changes needed to the shared x-input component.') }}</p>

            <div class="max-w-sm" x-data="{ show: false }">
                <x-input-label for="ks_password_toggle" class="mb-1.5">{{ __('Password') }}</x-input-label>
                <div class="relative">
                    <input
                        :type="show ? 'text' : 'password'"
                        id="ks_password_toggle"
                        name="ks_password_toggle"
                        placeholder="{{ __('Enter your password') }}"
                        class="block w-full rounded-md border border-otl-gray-300 bg-bg-white pe-10 text-body-sm text-txt-black-900 shadow-sm focus:border-primary-600 focus:ring-1 focus:ring-fr-primary"
                    >
                    <button
                        type="button"
                        x-on:click="show = !show"
                        class="absolute inset-y-0 end-0 flex w-10 items-center justify-center text-txt-black-500 hover:text-txt-black-900"
                        :aria-label="show ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'"
                    >
                        <svg x-show="!show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <svg x-show="show" style="display: none;" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Search Bar') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('The navbar quick-search dropdown, plus a standalone inline variant for use inside a page (e.g. filtering a table).') }}</p>

            <div class="space-y-4">
                <div>
                    <p class="mb-2 text-body-xs font-medium text-txt-black-500">{{ __('Navbar variant') }}</p>
                    <x-quick-search />
                </div>

                <div class="max-w-sm">
                    <p class="mb-2 text-body-xs font-medium text-txt-black-500">{{ __('Inline variant') }}</p>
                    <div class="relative">
                        <svg class="pointer-events-none absolute inset-y-0 start-3 my-auto h-4 w-4 text-txt-black-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        <input
                            type="search"
                            placeholder="{{ __('Search...') }}"
                            class="block w-full rounded-md border border-otl-gray-300 bg-bg-white py-2 ps-9 text-body-sm text-txt-black-900 shadow-sm focus:border-primary-600 focus:ring-1 focus:ring-fr-primary"
                        >
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Character Count') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('An optional maxlength prop on x-textarea shows a live counter as you type.') }}</p>

            <div class="max-w-2xl">
                <x-textarea label="{{ __('Short bio') }}" name="ks_bio_counted" rows="3" maxlength="120" placeholder="{{ __('Tell us a little about yourself...') }}" />
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Date Field') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('A plain formatted-text date field — distinct from the popup Calendar, useful when the exact display format matters more than a picker UI.') }}</p>

            <div class="max-w-xs">
                <x-input label="{{ __('Date of Birth') }}" name="ks_date_field" placeholder="DD/MM/YYYY" help="{{ __('Format: DD/MM/YYYY') }}" />
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Select & Checkbox') }}</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                <div>
                    <x-input-label for="ks_role" class="mb-1.5">{{ __('Role') }}</x-input-label>
                    <select id="ks_role" class="block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm">
                        <option>{{ __('User') }}</option>
                        <option>{{ __('Admin') }}</option>
                    </select>
                </div>

                <label class="flex items-center gap-2 mt-7">
                    <input type="checkbox" checked class="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary">
                    <span class="text-body-sm text-txt-black-900">{{ __('Active') }}</span>
                </label>

                <div>
                    <x-input-label for="ks_permissions" class="mb-1.5">{{ __('Permissions') }}</x-input-label>
                    <select
                        id="ks_permissions"
                        multiple
                        size="4"
                        class="block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm"
                    >
                        <option selected>{{ __('View Users') }}</option>
                        <option selected>{{ __('Edit Users') }}</option>
                        <option>{{ __('Delete Users') }}</option>
                        <option>{{ __('Manage Roles') }}</option>
                        <option>{{ __('View Reports') }}</option>
                    </select>
                    <p class="mt-1 text-body-xs text-txt-black-500">{{ __('Hold Cmd/Ctrl (or Shift for a range) to select more than one.') }}</p>
                </div>
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Date Picker') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('Native browser date inputs — the calendar UI is provided by the OS/browser, no JS library needed.') }}</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                <x-input label="{{ __('Date') }}" type="date" name="ks_date" />
                <x-input label="{{ __('Date/Time') }}" type="datetime-local" name="ks_datetime" />
                <x-input label="{{ __('Start Date') }}" type="date" name="ks_date_start" />
                <x-input label="{{ __('End Date') }}" type="date" name="ks_date_end" />
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Calendar') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('Click the month or year label to drill down; click again to return to the day view.') }}</p>

            <x-calendar selected="2024-08-14" max="2024-09-30" />
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Duration Picker') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('Same calendar, in range mode — click a start date, then an end date. Dates in between highlight automatically.') }}</p>

            <x-calendar range />
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Textarea') }}</h3>

            <div class="max-w-2xl">
                <x-textarea label="{{ __('Bio') }}" name="ks_bio" placeholder="{{ __('Tell us a little about yourself...') }}" help="{{ __('Max 500 characters.') }}" />
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Radio Group') }}</h3>

            <div>
                <x-input-label class="mb-1.5">{{ __('Role') }}</x-input-label>
                <div class="flex flex-wrap gap-4">
                    <x-radio name="ks_role_radio" value="admin" label="{{ __('Admin') }}" checked />
                    <x-radio name="ks_role_radio" value="user" label="{{ __('User') }}" />
                </div>
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Toggle') }}</h3>

            <div class="flex flex-wrap gap-6">
                <x-toggle name="ks_toggle_on" label="{{ __('Email notifications') }}" checked />
                <x-toggle name="ks_toggle_off" label="{{ __('SMS notifications') }}" />
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-6">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('File Upload') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('Matches the MYDS file upload pattern exactly — try selecting a file over 1MB on the first example to see the Error state, or any file to see the brief Loading state.') }}</p>
            </div>

            <div class="space-y-4 max-w-2xl">
                <div>
                    <p class="mb-2 text-body-xs font-medium text-txt-black-500">{{ __('Default') }}</p>
                    <x-file-upload
                        title="{{ __('Upload Required Documents') }}"
                        name="ks_file"
                        formats="JPG, PNG & PDF"
                        :max-size-mb="1"
                    />
                </div>

                <div>
                    <p class="mb-2 text-body-xs font-medium text-txt-black-500">{{ __('Disabled') }}</p>
                    <x-file-upload
                        title="{{ __('Upload Required Documents') }}"
                        name="ks_file_disabled"
                        formats="JPG, PNG & PDF"
                        :max-size-mb="25"
                        disabled
                    />
                </div>

                <div>
                    <p class="mb-2 text-body-xs font-medium text-txt-black-500">{{ __('Uploaded, with previously uploaded files') }}</p>
                    <x-file-upload
                        title="{{ __('Upload Required Documents') }}"
                        name="ks_file_previous"
                        formats="JPG, PNG & PDF"
                        :max-size-mb="25"
                        :previous-files="[
                            ['name' => 'Document.pdf', 'size' => '1.2 MB'],
                            ['name' => 'Document.pdf', 'size' => '1.2 MB', 'disabled' => true],
                        ]"
                    />
                </div>
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Complete Form Example') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('The same components composed into a realistic form, matching the pattern used by the User Management CRUD.') }}</p>

            <form class="max-w-2xl space-y-4" onsubmit="return false">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-input label="{{ __('Name') }}" name="ks_form_name" required placeholder="Jane Doe" />
                    <x-input label="{{ __('Email') }}" type="email" name="ks_form_email" required placeholder="jane@example.com" />
                </div>

                <x-textarea label="{{ __('Bio') }}" name="ks_form_bio" rows="3" placeholder="{{ __('Tell us a little about yourself...') }}" />

                <div>
                    <x-input-label class="mb-1.5">{{ __('Role') }}</x-input-label>
                    <div class="flex flex-wrap gap-4">
                        <x-radio name="ks_form_role" value="admin" label="{{ __('Admin') }}" />
                        <x-radio name="ks_form_role" value="user" label="{{ __('User') }}" checked />
                    </div>
                </div>

                <x-toggle name="ks_form_active" label="{{ __('Active') }}" checked />

                <div class="flex justify-end gap-3 pt-2">
                    <x-button variant="secondary" type="reset">{{ __('Cancel') }}</x-button>
                    <x-button variant="primary" type="submit">{{ __('Save') }}</x-button>
                </div>
            </form>
        </section>
    </div>
</x-admin-layout>
