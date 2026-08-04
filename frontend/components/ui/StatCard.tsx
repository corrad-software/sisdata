import type { ReactNode } from 'react';

type Trend = 'up' | 'down' | 'neutral';

const TREND_STYLES: Record<Trend, string> = {
    up: 'text-txt-success bg-bg-success-50',
    down: 'text-txt-danger bg-bg-danger-50',
    neutral: 'text-txt-black-500 bg-bg-washed',
};

export function StatCard({
    label,
    value,
    change,
    trend = 'neutral',
    className,
    children,
}: {
    label: string;
    value: string;
    change?: string;
    trend?: Trend;
    className?: string;
    children?: ReactNode;
}) {
    return (
        <div className={`rounded-lg border border-otl-gray-200 bg-bg-white p-4 shadow-card ${className ?? ''}`}>
            <p className="text-body-xs font-medium text-txt-black-500">{label}</p>
            <div className="mt-1.5 flex items-end justify-between gap-2">
                <p className="font-heading text-heading-xs font-semibold text-txt-black-900">{value}</p>

                {change && (
                    <span
                        className={`inline-flex flex-shrink-0 items-center gap-0.5 rounded-full ${TREND_STYLES[trend]} px-2 py-0.5 text-body-xs font-medium`}
                    >
                        {trend === 'up' && (
                            <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                            </svg>
                        )}
                        {trend === 'down' && (
                            <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        )}
                        {change}
                    </span>
                )}
            </div>

            {children && <div className="mt-3">{children}</div>}
        </div>
    );
}
