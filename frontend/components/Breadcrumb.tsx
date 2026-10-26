import Link from 'next/link';

export interface BreadcrumbItem {
    label: string;
    href?: string;
}

export function Breadcrumb({ items }: { items: BreadcrumbItem[] }) {
    return (
        <nav aria-label="Laluan navigasi" className="flex">
            <ol className="flex flex-wrap items-center gap-1.5 text-body-xs">
                <li>
                    <Link href="/admin" className="text-txt-black-500 hover:text-txt-black-900">
                        Utama
                    </Link>
                </li>

                {items.map((item, index) => {
                    const isLast = index === items.length - 1;

                    return (
                        <li key={item.label} className="flex items-center gap-1.5">
                            <svg className="h-3.5 w-3.5 text-txt-black-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>

                            {item.href && !isLast ? (
                                <Link href={item.href} className="text-txt-black-500 hover:text-txt-black-900">
                                    {item.label}
                                </Link>
                            ) : (
                                <span className="font-medium text-txt-black-900" aria-current="page">
                                    {item.label}
                                </span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
