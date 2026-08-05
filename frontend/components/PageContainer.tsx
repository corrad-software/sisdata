import type { ReactNode } from 'react';

export function PageContainer({ children, className = 'space-y-6' }: { children: ReactNode; className?: string }) {
    return <div className={`py-8 px-4 sm:px-6 lg:px-8 ${className}`}>{children}</div>;
}
