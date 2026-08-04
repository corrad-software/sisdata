@php
    $footerText = \App\Models\Setting::get('footer_text');
    $siteTitle = \App\Models\Setting::get('site_title', config('app.name', 'Laravel'));
@endphp

<footer class="border-t border-otl-divider bg-bg-white">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col items-center justify-between gap-2 text-body-xs text-txt-black-500 sm:flex-row">
            <p>{{ $footerText ?: __('© :year :app. Built on the Malaysian Government Design System (MYDS).', ['year' => date('Y'), 'app' => $siteTitle]) }}</p>
            <a href="https://design.digital.gov.my" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:underline">
                design.digital.gov.my
            </a>
        </div>
    </div>
</footer>
