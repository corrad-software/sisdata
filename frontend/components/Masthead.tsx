/* eslint-disable @next/next/no-img-element -- a 14px crest; next/image adds nothing here */
export function Masthead() {
    return (
        <div className="bg-bg-black-900 text-txt-white">
            <div className="mx-auto flex max-w-7xl items-center gap-2 px-4 py-1.5 text-body-xs sm:px-6 lg:px-8">
                <img src="/branding/logo-mais.png" alt="" aria-hidden="true" className="h-4 w-4 flex-shrink-0 rounded-sm bg-white object-contain p-px" />
                <span>Laman web rasmi Majlis Agama Islam Selangor</span>
            </div>
        </div>
    );
}
