import Link from 'next/link';

/** SRS-GEN-09: Malay 404 instead of Next's default English one. */
export default function NotFound() {
    return (
        <main className="min-h-screen flex flex-col items-center justify-center gap-3 bg-bg-washed px-6 text-center">
            <p className="font-heading text-heading-md font-semibold text-primary-600">404</p>
            <h1 className="font-heading text-heading-xs font-semibold text-txt-black-900">Halaman tidak ditemui</h1>
            <p className="max-w-md text-body-sm text-txt-black-500">
                Halaman yang anda cari tidak wujud atau telah dipindahkan.
            </p>
            <Link href="/admin" className="mt-2 text-body-sm font-medium text-primary-600 hover:text-primary-700">
                Kembali ke halaman utama
            </Link>
        </main>
    );
}
