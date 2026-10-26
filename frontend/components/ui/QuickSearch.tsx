import { Dropdown } from './Dropdown';

export function QuickSearch() {
    return (
        <Dropdown
            align="left"
            width="w-72"
            contentClassName="bg-bg-white p-3"
            trigger={
                <button
                    type="button"
                    className="inline-flex items-center justify-center rounded-md p-2 text-txt-black-500 hover:text-txt-black-900 hover:bg-bg-washed focus:outline-none transition ease-in-out duration-150"
                    aria-label="Cari"
                >
                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"
                        />
                    </svg>
                </button>
            }
        >
            <form onSubmit={(e) => e.preventDefault()} className="flex items-center gap-2">
                <input
                    type="search"
                    placeholder="Cari pengguna mengikut nama atau e-mel..."
                    className="block w-64 rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm"
                />
                <button
                    type="submit"
                    className="inline-flex items-center justify-center rounded-md bg-primary-600 p-2 text-white hover:bg-primary-700 focus:outline-none transition ease-in-out duration-150"
                    aria-label="Cari"
                >
                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"
                        />
                    </svg>
                </button>
            </form>
        </Dropdown>
    );
}
