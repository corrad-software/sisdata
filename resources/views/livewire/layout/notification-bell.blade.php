<div wire:poll.15s>
    <x-dropdown align="right" width="w-80" content-classes="bg-bg-white">
        <x-slot name="trigger">
            <button
                type="button"
                class="relative inline-flex items-center justify-center rounded-md p-2 text-txt-black-500 hover:text-txt-black-900 hover:bg-bg-washed focus:outline-none transition ease-in-out duration-150"
                aria-label="{{ __('Notifications') }}"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                </svg>

                @if ($unreadCount > 0)
                    <span class="absolute -top-0.5 -right-0.5 inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-danger-600 px-1 text-[10px] font-semibold leading-none text-white">
                        {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                    </span>
                @endif
            </button>
        </x-slot>

        <x-slot name="content">
            <div class="flex items-center justify-between px-4 py-2 border-b border-otl-divider">
                <span class="text-body-sm font-semibold text-txt-black-900">{{ __('Notifications') }}</span>

                @if ($unreadCount > 0)
                    <button type="button" wire:click="markAllAsRead" class="text-body-xs text-primary-600 hover:underline">
                        {{ __('Mark all as read') }}
                    </button>
                @endif
            </div>

            <div class="max-h-80 overflow-y-auto">
                @forelse ($notifications as $notification)
                    <button
                        type="button"
                        wire:click="markAsRead('{{ $notification->id }}')"
                        class="block w-full px-4 py-3 text-start text-body-sm border-b border-otl-divider last:border-0 hover:bg-bg-washed {{ $notification->read_at ? 'text-txt-black-500' : 'text-txt-black-900 bg-bg-primary-50' }}"
                    >
                        <p>{{ $notification->data['message'] }}</p>
                        <p class="mt-0.5 text-body-xs text-txt-black-500">{{ $notification->created_at->diffForHumans() }}</p>
                    </button>
                @empty
                    <p class="px-4 py-6 text-center text-body-sm text-txt-black-500">{{ __('No notifications yet.') }}</p>
                @endforelse
            </div>
        </x-slot>
    </x-dropdown>
</div>
