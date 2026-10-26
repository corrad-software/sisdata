/**
 * SISDATA mark: a land parcel (with its boundary grid) under a location pin —
 * the system is the single source of truth for every MAIS lot. Uses
 * currentColor so it follows the theme; replace via Site Settings → logo when
 * MAIS supplies its official artwork.
 */
export function ApplicationLogo({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" className={className} role="img" aria-label="SISDATA">
            <path d="M6 40.5 32 27l26 13.5L32 54 6 40.5Z" fillOpacity="0.35" />
            <path
                fillRule="evenodd"
                d="M32 25.6 4.2 40.1a1 1 0 0 0 0 1.8L31.5 56a1 1 0 0 0 1 0l27.3-14.1a1 1 0 0 0 0-1.8L32.5 25.6a1 1 0 0 0-.5 0Zm0 3.1L9.4 40.5 32 52.2l22.6-11.7L32 28.7Z"
            />
            <path d="m19 34 23.2 12-1.6.8L17.4 34.8 19 34Zm8-4.2 23.2 12-1.6.8-23.2-12 1.6-.8Zm-5.3 18.3L45.1 36l1.6.8L23.3 49l-1.6-.9Zm-8-4.1L37 31.9l1.6.8-23.3 12-1.6-.7Z" fillOpacity="0.6" />
            <path
                fillRule="evenodd"
                d="M32 4c-7.2 0-13 5.6-13 12.6 0 8.6 10.6 19.1 12.1 20.6a1.3 1.3 0 0 0 1.8 0C34.4 35.7 45 25.2 45 16.6 45 9.6 39.2 4 32 4Zm0 18.2a5.6 5.6 0 1 0 0-11.2 5.6 5.6 0 0 0 0 11.2Z"
            />
        </svg>
    );
}
