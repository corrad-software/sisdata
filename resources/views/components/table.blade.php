@props(['head' => null])

<div class="overflow-x-auto rounded-lg border border-otl-gray-200 shadow-card">
    <table {{ $attributes->merge(['class' => 'min-w-full divide-y divide-otl-gray-200 text-left text-body-sm']) }}>
        @if ($head)
            <thead class="bg-bg-washed">
                <tr class="[&>th]:px-4 [&>th]:py-3 [&>th]:font-semibold [&>th]:text-txt-black-700 [&>th]:whitespace-nowrap">
                    {{ $head }}
                </tr>
            </thead>
        @endif

        <tbody class="divide-y divide-otl-gray-200 bg-white [&>tr>td]:px-4 [&>tr>td]:py-3 [&>tr>td]:text-txt-black-900 [&>tr:hover]:bg-bg-washed">
            {{ $slot }}
        </tbody>
    </table>
</div>
