export function Footer({
    siteTitle = 'SISDATA',
    footerText,
}: {
    siteTitle?: string;
    footerText?: string | null;
}) {
    return (
        <footer className="border-t border-otl-divider bg-bg-white">
            <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div className="flex flex-col items-center justify-between gap-2 text-body-xs text-txt-black-500 sm:flex-row">
                    <p>{footerText || `© ${new Date().getFullYear()} ${siteTitle}. Dibangunkan menggunakan Sistem Reka Bentuk Kerajaan Malaysia (MYDS).`}</p>
                    <a
                        href="https://sisdata.mais.gov.my"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-primary-600 hover:underline"
                    >
                        sisdata.mais.gov.my
                    </a>
                </div>
            </div>
        </footer>
    );
}
