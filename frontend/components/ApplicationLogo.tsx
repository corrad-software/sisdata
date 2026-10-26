/**
 * Majlis Agama Islam Selangor crest, used as the SISDATA logo (supplied by
 * MAIS). Site Settings → logo can still override it per deployment.
 */
export function ApplicationLogo({ className }: { className?: string }) {
    // eslint-disable-next-line @next/next/no-img-element -- static brand asset; next/image adds nothing here
    return <img src="/branding/logo-mais.png" alt="Majlis Agama Islam Selangor" className={`object-contain ${className ?? ''}`} />;
}
