<div class="flex items-center gap-1">
    <x-quick-search class="hidden sm:inline-flex" />
    <x-font-size-toggle class="hidden sm:inline-flex" />
    <x-theme-toggle />
    <x-language-switcher class="hidden sm:inline-flex" />
    <livewire:layout.notification-bell />

    <x-dropdown align="right" width="48" class="ms-2">
        <x-slot name="trigger">
            <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-txt-black-500 bg-bg-white hover:text-txt-black-900 focus:outline-none transition ease-in-out duration-150">
                <div>{{ auth()->user()->name }}</div>

                <div class="ms-1">
                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </div>
            </button>
        </x-slot>

        <x-slot name="content">
            <x-dropdown-link :href="route('profile')" wire:navigate>
                {{ __('Profile') }}
            </x-dropdown-link>

            <button wire:click="logout" class="w-full text-start">
                <x-dropdown-link>
                    {{ __('Log Out') }}
                </x-dropdown-link>
            </button>
        </x-slot>
    </x-dropdown>
</div>
