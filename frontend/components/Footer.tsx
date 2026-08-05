export function Footer({
    siteTitle = 'MYDS-Laravel',
    footerText,
}: {
    siteTitle?: string;
    footerText?: string | null;
}) {
    return (
        <footer className="border-t border-otl-divider bg-bg-white">
            <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div className="flex flex-col items-center justify-between gap-2 text-body-xs text-txt-black-500 sm:flex-row">
                    <p>{footerText || `© ${new Date().getFullYear()} ${siteTitle}. Built on the Malaysian Government Design System (MYDS).`}</p>
                    <a
                        href="https://design.digital.gov.my"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-primary-600 hover:underline"
                    >
                        design.digital.gov.my
                    </a>
                </div>
            </div>
        </footer>
    );
}
