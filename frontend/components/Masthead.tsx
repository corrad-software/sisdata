export function Masthead() {
    return (
        <div className="bg-bg-black-900 text-txt-white">
            <div className="mx-auto flex max-w-7xl items-center gap-2 px-4 py-1.5 text-body-xs sm:px-6 lg:px-8">
                <svg className="h-3.5 w-5 flex-shrink-0" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect width="30" height="20" fill="#010066" />
                    <g fill="#fff">
                        <rect y="0" width="30" height="1.43" />
                        <rect y="2.86" width="30" height="1.43" />
                        <rect y="5.72" width="30" height="1.43" />
                        <rect y="8.58" width="30" height="1.43" />
                        <rect y="11.44" width="30" height="1.43" />
                        <rect y="14.3" width="30" height="1.43" />
                        <rect y="17.16" width="30" height="1.43" />
                    </g>
                </svg>
                <span>An official website of the Malaysian Government</span>
            </div>
        </div>
    );
}
