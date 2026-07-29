<x-admin-layout :breadcrumbs="[
    ['label' => __('Admin'), 'url' => route('admin.dashboard')],
    ['label' => __('Kitchen Sink'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Components'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Content')],
]">
    @include('admin.kitchen-sink._tabs', ['group' => 'components'])

    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Accordion') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('One panel open at a time — click a heading to expand it.') }}</p>

            <x-accordion class="max-w-2xl" :items="[
                ['title' => __('What is MYDS?'), 'content' => __('The Malaysian Government Design System — a shared set of design tokens, components, and patterns for building consistent government digital services.')],
                ['title' => __('Can I use this outside government projects?'), 'content' => __('Yes — the design tokens and components in this starter are plain Tailwind/Blade and have no dependency on any government-specific service.')],
                ['title' => __('Where do I find more components?'), 'content' => __('See the Components tabs above, and the design.digital.gov.my documentation site for the full official reference.')],
            ]" />
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Details') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('The native HTML details/summary element, styled to match — works without any JavaScript at all.') }}</p>

            <details class="max-w-2xl rounded-md border border-otl-gray-200 bg-bg-white p-4 [&_summary]:cursor-pointer">
                <summary class="text-body-sm font-medium text-txt-black-900">{{ __('What data do we store about you?') }}</summary>
                <p class="mt-2 text-body-sm text-txt-black-500">{{ __('Your name, email address, and role — nothing else. See our privacy policy for full details.') }}</p>
            </details>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Panel') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('A simple bordered, optionally titled container — for grouping related content without the weight of a full card section.') }}</p>

            <x-panel title="{{ __('Account Summary') }}" class="max-w-2xl">
                <dl class="grid grid-cols-2 gap-3 text-body-sm">
                    <dt class="text-txt-black-500">{{ __('Plan') }}</dt>
                    <dd class="text-txt-black-900">{{ __('Government Standard') }}</dd>
                    <dt class="text-txt-black-500">{{ __('Seats used') }}</dt>
                    <dd class="text-txt-black-900">12 / 20</dd>
                </dl>
            </x-panel>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Inset Text') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('A left-border-highlighted block for quoting or setting apart a passage of text within body copy.') }}</p>

            <blockquote class="max-w-2xl border-s-4 border-otl-gray-300 ps-4 text-body-sm text-txt-black-700">
                {{ __('It will take up to 8 weeks to process your application. Contact us only if you have not heard back after this time.') }}
            </blockquote>
        </section>
    </div>
</x-admin-layout>
