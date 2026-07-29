<x-admin-layout :breadcrumbs="[
    ['label' => __('Admin'), 'url' => route('admin.dashboard')],
    ['label' => __('Kitchen Sink'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Foundation'), 'url' => route('admin.kitchen-sink.foundation.colour')],
    ['label' => __('Typography')],
]">
    @include('admin.kitchen-sink._tabs', ['group' => 'foundation'])

    @php
        $headings = [
            ['class' => 'text-heading-xl', 'name' => 'heading-xl', 'size' => '60px / 72px'],
            ['class' => 'text-heading-lg', 'name' => 'heading-lg', 'size' => '48px / 60px'],
            ['class' => 'text-heading-md', 'name' => 'heading-md', 'size' => '36px / 44px'],
            ['class' => 'text-heading-sm', 'name' => 'heading-sm', 'size' => '30px / 38px'],
            ['class' => 'text-heading-xs', 'name' => 'heading-xs', 'size' => '24px / 32px'],
            ['class' => 'text-heading-2xs', 'name' => 'heading-2xs', 'size' => '20px / 28px'],
        ];

        $body = [
            ['class' => 'text-body-xl', 'name' => 'body-xl', 'size' => '20px / 30px'],
            ['class' => 'text-body-lg', 'name' => 'body-lg', 'size' => '18px / 26px'],
            ['class' => 'text-body-md', 'name' => 'body-md', 'size' => '16px / 24px'],
            ['class' => 'text-body-sm', 'name' => 'body-sm', 'size' => '14px / 20px'],
            ['class' => 'text-body-xs', 'name' => 'body-xs', 'size' => '12px / 18px'],
        ];
    @endphp

    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-6">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Font Families') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('Three families cover every use case: headings, body copy, and monospace/code.') }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div class="rounded-md border border-otl-gray-200 p-4">
                    <p class="mb-2 text-body-xs font-medium uppercase tracking-wide text-txt-black-500">font-heading &mdash; Poppins</p>
                    <p class="font-heading text-heading-xs text-txt-black-900">Aa Bb Cc 123</p>
                </div>
                <div class="rounded-md border border-otl-gray-200 p-4">
                    <p class="mb-2 text-body-xs font-medium uppercase tracking-wide text-txt-black-500">font-body &mdash; Inter</p>
                    <p class="font-body text-heading-xs text-txt-black-900">Aa Bb Cc 123</p>
                </div>
                <div class="rounded-md border border-otl-gray-200 p-4">
                    <p class="mb-2 text-body-xs font-medium uppercase tracking-wide text-txt-black-500">font-mono &mdash; Roboto Mono</p>
                    <p class="font-mono text-heading-xs text-txt-black-900">Aa Bb Cc 123</p>
                </div>
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Heading Scale') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('Used with font-heading. Every page title and section heading in this app uses one of these.') }}</p>
            </div>

            <div class="divide-y divide-otl-gray-200">
                @foreach ($headings as $item)
                    <div class="flex flex-col gap-1 py-3 sm:flex-row sm:items-baseline sm:gap-4">
                        <p class="w-32 flex-shrink-0 font-mono text-body-xs text-txt-black-500">{{ $item['name'] }}</p>
                        <p class="{{ $item['class'] }} font-heading font-semibold text-txt-black-900">{{ __('The quick brown fox') }}</p>
                        <p class="ms-auto flex-shrink-0 font-mono text-body-xs text-txt-black-500">{{ $item['size'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Body Scale') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('Used with font-body. body-sm is the default size for most UI text in this app.') }}</p>
            </div>

            <div class="divide-y divide-otl-gray-200">
                @foreach ($body as $item)
                    <div class="flex flex-col gap-1 py-3 sm:flex-row sm:items-baseline sm:gap-4">
                        <p class="w-32 flex-shrink-0 font-mono text-body-xs text-txt-black-500">{{ $item['name'] }}</p>
                        <p class="{{ $item['class'] }} font-body text-txt-black-900">{{ __('The quick brown fox jumps over the lazy dog.') }}</p>
                        <p class="ms-auto flex-shrink-0 font-mono text-body-xs text-txt-black-500">{{ $item['size'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-admin-layout>
