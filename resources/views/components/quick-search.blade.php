<div {{ $attributes }}>
    <x-dropdown align="left" width="72" content-classes="bg-bg-white p-3">
        <x-slot name="trigger">
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-md p-2 text-txt-black-500 hover:text-txt-black-900 hover:bg-bg-washed focus:outline-none transition ease-in-out duration-150"
                aria-label="{{ __('Search') }}"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
            </button>
        </x-slot>

        <x-slot name="content">
            <form action="{{ route('admin.users.index') }}" method="GET" class="flex items-center gap-2">
                <input
                    type="search"
                    name="search"
                    placeholder="{{ __('Search users by name or email...') }}"
                    class="block w-64 rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm"
                >
                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-md bg-primary-600 p-2 text-white hover:bg-primary-700 focus:outline-none transition ease-in-out duration-150"
                    aria-label="{{ __('Search') }}"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                </button>
            </form>
        </x-slot>
    </x-dropdown>
</div>
