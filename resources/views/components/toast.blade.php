{{--
    Floating toast stack. Include once per page/layout, then trigger from
    anywhere with:
        x-on:click="$dispatch('toast', { type: 'success', message: '...' })"
    `type` is one of: success, error, warning, info.
--}}
<div
    x-data="{
        toasts: [],
        add(type, message) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, type, message });
            setTimeout(() => this.remove(id), 4000);
        },
        remove(id) {
            this.toasts = this.toasts.filter((t) => t.id !== id);
        },
    }"
    x-on:toast.window="add($event.detail.type ?? 'info', $event.detail.message)"
    class="pointer-events-none fixed top-4 right-4 z-50 flex w-full max-w-sm flex-col gap-2"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-x-4"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="pointer-events-auto flex items-start gap-3 rounded-md border px-4 py-3 text-body-sm shadow-card"
            :class="{
                'bg-bg-success-50 border-otl-success-200 text-txt-success': toast.type === 'success',
                'bg-bg-danger-50 border-otl-danger-200 text-txt-danger': toast.type === 'error',
                'bg-bg-warning-50 border-otl-warning-200 text-txt-warning': toast.type === 'warning',
                'bg-bg-primary-50 border-otl-primary-200 text-txt-primary': toast.type === 'info',
            }"
        >
            <div class="flex-1" x-text="toast.message"></div>
            <button type="button" x-on:click="remove(toast.id)" class="flex-shrink-0 opacity-70 hover:opacity-100" aria-label="{{ __('Dismiss') }}">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </template>
</div>
