@props([
    'title' => null,
    'formats' => 'JPG, PNG & PDF',
    'maxSizeMb' => 25,
    'name' => null,
    'multiple' => true,
    'disabled' => false,
    'previousFiles' => [],
])

@php
$id = $attributes->get('id') ?? $name;
$refKey = $id.'-input';
@endphp

<div
    x-data="{
        files: [],
        loading: false,
        error: null,
        maxSizeMb: {{ $maxSizeMb }},
        multiple: @js($multiple),
        formatSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        },
        extensionOf(name) {
            const parts = name.split('.');
            return parts.length > 1 ? parts.pop().toUpperCase() : '';
        },
        addFiles(fileList) {
            this.error = null;
            const incoming = Array.from(fileList);
            const accepted = [];
            for (const file of incoming) {
                if (file.size > this.maxSizeMb * 1024 * 1024) {
                    this.error = '{{ __('Please upload file smaller than') }} ' + this.maxSizeMb + 'MB.';
                    continue;
                }
                accepted.push(file);
            }
            if (! accepted.length) return;
            this.loading = true;
            setTimeout(() => {
                this.files = this.multiple ? [...this.files, ...accepted] : accepted.slice(-1);
                this.loading = false;
            }, 600);
        },
        removeFile(index) {
            this.files.splice(index, 1);
        },
    }"
    {{ $attributes->merge(['class' => 'rounded-lg border border-otl-gray-200 bg-bg-white p-4']) }}
>
    <div class="flex items-start justify-between gap-4">
        <div>
            @if ($title)
                <h4 class="text-body-sm font-semibold text-txt-black-900">{{ $title }}</h4>
            @endif
            <p class="text-body-xs text-txt-black-500">{{ __('File types:') }} {{ $formats }}</p>
            <p class="text-body-xs text-txt-black-500">{{ __('Max file size:') }} {{ $maxSizeMb }}MB</p>
        </div>

        <button
            type="button"
            x-on:click="$refs['{{ $refKey }}'].click()"
            @disabled($disabled)
            class="inline-flex flex-shrink-0 items-center gap-1.5 rounded-md border border-otl-gray-300 px-3 py-2 text-body-sm font-medium text-txt-black-900 hover:bg-bg-washed focus:outline-none focus-visible:ring-2 focus-visible:ring-fr-primary disabled:cursor-not-allowed disabled:opacity-50"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            {{ __('Upload') }}
        </button>

        <input
            type="file"
            id="{{ $id }}"
            x-ref="{{ $refKey }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($multiple) multiple @endif
            @disabled($disabled)
            x-on:change="addFiles($event.target.files)"
            class="hidden"
        >
    </div>

    <p x-show="error" x-text="error" style="display: none" class="mt-3 text-body-xs text-danger-600"></p>

    <div x-show="loading" style="display: none" class="mt-3 flex items-center gap-2.5 rounded-md border border-otl-gray-200 px-3 py-2.5">
        <x-spinner size="sm" class="flex-shrink-0 text-txt-black-500" />
        <span class="text-body-sm text-txt-black-500">{{ __('Uploading...') }}</span>
    </div>

    <div x-show="files.length > 0" style="display: none" class="mt-3 flex flex-wrap gap-3 border-t border-otl-gray-200 pt-3">
        <template x-for="(file, index) in files" :key="file.name + file.size + index">
            <div class="flex items-center gap-2.5 rounded-md border border-otl-gray-200 px-3 py-2">
                <span
                    class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded text-[9px] font-bold text-white"
                    :class="{
                        'bg-danger-600': extensionOf(file.name) === 'PDF',
                        'bg-primary-600': ['JPG', 'JPEG', 'PNG'].includes(extensionOf(file.name)),
                        'bg-bg-black-500': ! ['PDF', 'JPG', 'JPEG', 'PNG'].includes(extensionOf(file.name)),
                    }"
                    x-text="extensionOf(file.name)"
                ></span>
                <div class="min-w-0">
                    <p class="truncate text-body-sm font-medium text-txt-black-900" x-text="file.name"></p>
                    <p class="text-body-xs text-txt-black-500" x-text="formatSize(file.size)"></p>
                </div>
                <button
                    type="button"
                    x-on:click="removeFile(index)"
                    class="ms-1 flex-shrink-0 text-danger-600 hover:text-danger-700"
                    aria-label="{{ __('Remove file') }}"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </template>
    </div>

    @if (count($previousFiles))
        <div class="mt-3 border-t border-otl-gray-200 pt-3">
            <p class="mb-2 text-body-xs text-txt-black-500">{{ __('Previously uploaded') }}</p>

            <div class="flex flex-wrap gap-3">
                @foreach ($previousFiles as $file)
                    @php $isDisabled = $file['disabled'] ?? false; @endphp
                    <div class="flex items-center gap-2.5 rounded-md border border-otl-gray-200 px-3 py-2 {{ $isDisabled ? 'opacity-50' : '' }}">
                        <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded bg-danger-600 text-[9px] font-bold text-white">
                            {{ strtoupper(pathinfo($file['name'], PATHINFO_EXTENSION)) }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-body-sm font-medium text-txt-black-900">{{ $file['name'] }}</p>
                            <p class="text-body-xs text-txt-black-500">{{ $file['size'] }}</p>
                        </div>
                        @unless ($isDisabled)
                            <button type="button" class="ms-1 flex-shrink-0 text-danger-600 hover:text-danger-700" aria-label="{{ __('Remove file') }}">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        @endunless
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
