import type { ReactNode } from 'react';

export function InputLabel({
    htmlFor,
    children,
    className,
}: {
    htmlFor?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <label htmlFor={htmlFor} className={`block font-medium text-body-sm text-txt-black-900 ${className ?? ''}`}>
            {children}
        </label>
    );
}
