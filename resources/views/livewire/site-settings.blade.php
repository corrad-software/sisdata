<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if (session('status'))
            <x-alert type="success" dismissible>{{ session('status') }}</x-alert>
        @endif

        <div class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Site Identity') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('The site title is shown in the browser tab and page headers.') }}</p>
            </div>

            <div class="max-w-sm">
                <x-input label="{{ __('Site Title') }}" wire:model="siteTitle" name="siteTitle" required error="{{ $errors->first('siteTitle') }}" />
            </div>
        </div>

        <div class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Footer') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('Shown at the bottom of every page. Leave blank to use the default copyright line.') }}</p>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                <div class="max-w-md flex-1">
                    <x-input
                        label="{{ __('Footer Text') }}"
                        wire:model="footerText"
                        name="footerText"
                        placeholder="© {{ date('Y') }} {{ $siteTitle }}. All rights reserved."
                        error="{{ $errors->first('footerText') }}"
                    />
                </div>

                <x-button variant="secondary" type="button" wire:click="resetFooterText">
                    {{ __('Reset to Default') }}
                </x-button>
            </div>
        </div>

        <div class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Theme Colour') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('Pick your primary brand colour — the full colour scale (buttons, links, focus rings) regenerates from it automatically.') }}</p>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                <div class="max-w-xs flex-1">
                    <x-color-picker
                        label="{{ __('Primary Colour') }}"
                        wire:model="primaryColor"
                        name="primaryColor"
                        help="{{ __('Used for buttons, links, and focus rings across the app.') }}"
                    />
                    @error('primaryColor') <p class="mt-1 text-body-xs text-danger-600">{{ $message }}</p> @enderror
                </div>

                <x-button variant="secondary" type="button" wire:click="resetColor">
                    {{ __('Reset to Default') }}
                </x-button>
            </div>

            <div class="flex flex-wrap items-center gap-3 rounded-md border border-otl-gray-200 bg-bg-washed p-4">
                <span class="text-body-xs font-medium text-txt-black-500">{{ __('Preview:') }}</span>
                <x-button variant="primary" type="button">{{ __('Primary Button') }}</x-button>
                <x-badge variant="info">{{ __('Badge') }}</x-badge>
                <a href="#" class="text-body-sm font-medium text-primary-600 hover:text-primary-700">{{ __('A link') }}</a>
            </div>
        </div>

        <div class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Site Logo') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('Shown in the sidebar and public navbar. Falls back to the default MYDS logo mark if not set.') }}</p>
            </div>

            <div class="flex items-center gap-4">
                <div class="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-lg border border-otl-gray-200 bg-bg-washed p-2">
                    @if ($logo)
                        <img src="{{ $logo->temporaryUrl() }}" alt="{{ __('Logo preview') }}" class="max-h-full max-w-full object-contain">
                    @elseif ($currentLogoPath)
                        <img src="{{ asset('storage/'.$currentLogoPath) }}" alt="{{ __('Current logo') }}" class="max-h-full max-w-full object-contain">
                    @else
                        <x-application-logo class="h-8 w-auto fill-current text-primary-600" />
                    @endif
                </div>

                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-otl-gray-300 px-3 py-2 text-body-sm font-medium text-txt-black-900 hover:bg-bg-washed">
                            {{ __('Choose File') }}
                            <input type="file" wire:model="logo" accept="image/*" class="hidden">
                        </label>

                        @if ($currentLogoPath)
                            <button type="button" wire:click="removeLogo" wire:confirm="{{ __('Remove the current logo?') }}" class="text-body-sm font-medium text-danger-600 hover:text-danger-700">
                                {{ __('Remove') }}
                            </button>
                        @endif
                    </div>
                    <p class="text-body-xs text-txt-black-500">{{ __('PNG or SVG recommended, max 1MB.') }}</p>
                    @error('logo') <p class="text-body-xs text-danger-600">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="logo" class="flex items-center gap-2">
                        <x-spinner size="sm" class="text-txt-black-500" />
                        <span class="text-body-xs text-txt-black-500">{{ __('Uploading...') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Favicon') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('Shown in the browser tab. Square image recommended (e.g. 32×32 or 64×64).') }}</p>
            </div>

            <div class="flex items-center gap-4">
                <div class="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-lg border border-otl-gray-200 bg-bg-washed p-3">
                    @if ($favicon)
                        <img src="{{ $favicon->temporaryUrl() }}" alt="{{ __('Favicon preview') }}" class="max-h-full max-w-full object-contain">
                    @elseif ($currentFaviconPath)
                        <img src="{{ asset('storage/'.$currentFaviconPath) }}" alt="{{ __('Current favicon') }}" class="max-h-full max-w-full object-contain">
                    @else
                        <x-application-logo class="h-6 w-auto fill-current text-primary-600" />
                    @endif
                </div>

                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-otl-gray-300 px-3 py-2 text-body-sm font-medium text-txt-black-900 hover:bg-bg-washed">
                            {{ __('Choose File') }}
                            <input type="file" wire:model="favicon" accept="image/*" class="hidden">
                        </label>

                        @if ($currentFaviconPath)
                            <button type="button" wire:click="removeFavicon" wire:confirm="{{ __('Remove the current favicon?') }}" class="text-body-sm font-medium text-danger-600 hover:text-danger-700">
                                {{ __('Remove') }}
                            </button>
                        @endif
                    </div>
                    <p class="text-body-xs text-txt-black-500">{{ __('PNG or ICO recommended, max 512KB.') }}</p>
                    @error('favicon') <p class="text-body-xs text-danger-600">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="favicon" class="flex items-center gap-2">
                        <x-spinner size="sm" class="text-txt-black-500" />
                        <span class="text-body-xs text-txt-black-500">{{ __('Uploading...') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <x-button variant="primary" type="button" wire:click="save">
                {{ __('Save Settings') }}
            </x-button>
        </div>
    </div>

    <x-toast />
</div>
