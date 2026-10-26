function getPageElements(current: number, last: number, onEachSide: number): Array<number | 'ellipsis'> {
    const keep = new Set<number>([1, last]);

    for (let i = current - onEachSide; i <= current + onEachSide; i++) {
        if (i >= 1 && i <= last) keep.add(i);
    }

    const sorted = Array.from(keep).sort((a, b) => a - b);
    const elements: Array<number | 'ellipsis'> = [];
    let previous = 0;

    for (const page of sorted) {
        if (previous && page - previous > 1) elements.push('ellipsis');
        elements.push(page);
        previous = page;
    }

    return elements;
}

interface PaginationProps {
    currentPage: number;
    lastPage: number;
    onEachSide?: number;
    onPageChange?: (page: number) => void;
    firstItem?: number;
    lastItem?: number;
    total?: number;
}

// Mirrors Laravel's default `tailwind.blade.php` pagination view class-for-class
// (plain Tailwind grays, not MYDS tokens) since that's the literal output every
// `$paginator->links()` call in the Blade app renders today.
export function Pagination({ currentPage, lastPage, onEachSide = 1, onPageChange, firstItem, lastItem, total }: PaginationProps) {
    if (lastPage <= 1) return null;

    const elements = getPageElements(currentPage, lastPage, onEachSide);
    const onFirstPage = currentPage <= 1;
    const hasMorePages = currentPage < lastPage;

    function go(page: number) {
        if (page < 1 || page > lastPage) return;
        onPageChange?.(page);
    }

    return (
        <nav role="navigation" aria-label="Navigasi halaman">
            <div className="flex gap-2 items-center justify-between sm:hidden">
                <button
                    type="button"
                    onClick={() => go(currentPage - 1)}
                    disabled={onFirstPage}
                    className="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-800 bg-white border border-gray-300 leading-5 rounded-md hover:bg-gray-100 disabled:cursor-not-allowed disabled:text-gray-600 disabled:hover:bg-white"
                >
                    Sebelumnya
                </button>
                <button
                    type="button"
                    onClick={() => go(currentPage + 1)}
                    disabled={!hasMorePages}
                    className="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-800 bg-white border border-gray-300 leading-5 rounded-md hover:bg-gray-100 disabled:cursor-not-allowed disabled:text-gray-600 disabled:hover:bg-white"
                >
                    Seterusnya
                </button>
            </div>

            <div className="hidden sm:flex-1 sm:flex sm:gap-2 sm:items-center sm:justify-between">
                {total !== undefined && (
                    <div>
                        <p className="text-sm text-gray-700 leading-5">
                            Memaparkan <span className="font-medium">{firstItem}</span> hingga{' '}
                            <span className="font-medium">{lastItem}</span> daripada <span className="font-medium">{total}</span> rekod
                        </p>
                    </div>
                )}

                <div>
                    <span className="inline-flex shadow-sm rounded-md">
                        <button
                            type="button"
                            onClick={() => go(currentPage - 1)}
                            disabled={onFirstPage}
                            aria-label="Sebelumnya"
                            className="inline-flex items-center px-2 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-l-md leading-5 hover:bg-gray-100 disabled:cursor-not-allowed"
                        >
                            <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    fillRule="evenodd"
                                    clipRule="evenodd"
                                    d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
                                />
                            </svg>
                        </button>

                        {elements.map((el, index) =>
                            el === 'ellipsis' ? (
                                <span
                                    key={`ellipsis-${index}`}
                                    className="inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-gray-700 bg-white border border-gray-300 cursor-default leading-5"
                                >
                                    ...
                                </span>
                            ) : el === currentPage ? (
                                <span
                                    key={el}
                                    aria-current="page"
                                    className="inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-gray-700 bg-gray-200 border border-gray-300 cursor-default leading-5"
                                >
                                    {el}
                                </span>
                            ) : (
                                <button
                                    key={el}
                                    type="button"
                                    onClick={() => go(el)}
                                    aria-label={`Pergi ke halaman ${el}`}
                                    className="inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-gray-700 bg-white border border-gray-300 leading-5 hover:bg-gray-100"
                                >
                                    {el}
                                </button>
                            ),
                        )}

                        <button
                            type="button"
                            onClick={() => go(currentPage + 1)}
                            disabled={!hasMorePages}
                            aria-label="Seterusnya"
                            className="inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-r-md leading-5 hover:bg-gray-100 disabled:cursor-not-allowed"
                        >
                            <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    fillRule="evenodd"
                                    clipRule="evenodd"
                                    d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                />
                            </svg>
                        </button>
                    </span>
                </div>
            </div>
        </nav>
    );
}
