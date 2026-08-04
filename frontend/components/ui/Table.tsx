import type { ReactNode } from 'react';

export function Table({ head, children }: { head: ReactNode; children: ReactNode }) {
    return (
        <div className="overflow-x-auto rounded-lg border border-otl-gray-200 shadow-card">
            <table className="min-w-full divide-y divide-otl-gray-200 text-left text-body-sm">
                <thead className="bg-bg-washed">
                    <tr className="[&>th]:px-4 [&>th]:py-3 [&>th]:font-semibold [&>th]:text-txt-black-700 [&>th]:whitespace-nowrap">
                        {head}
                    </tr>
                </thead>

                <tbody className="divide-y divide-otl-gray-200 bg-bg-white [&>tr>td]:px-4 [&>tr>td]:py-3 [&>tr>td]:text-txt-black-900 [&>tr:hover]:bg-bg-washed">
                    {children}
                </tbody>
            </table>
        </div>
    );
}
