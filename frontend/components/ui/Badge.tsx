import type { ReactNode } from 'react';

type Variant = 'success' | 'danger' | 'warning' | 'info' | 'neutral';

const variants: Record<Variant, string> = {
    success: 'bg-bg-success-50 text-txt-success',
    danger: 'bg-bg-danger-50 text-txt-danger',
    warning: 'bg-bg-warning-50 text-txt-warning',
    info: 'bg-bg-primary-50 text-txt-primary',
    neutral: 'bg-bg-washed text-txt-black-500',
};

type Shape = 'pill' | 'tag';

export function Badge({
    variant = 'neutral',
    shape = 'pill',
    children,
}: {
    variant?: Variant;
    shape?: Shape;
    children: ReactNode;
}) {
    const shapeClass = shape === 'tag' ? 'rounded-md' : 'rounded-full';

    return (
        <span className={`inline-flex items-center ${shapeClass} px-2.5 py-0.5 text-body-xs font-medium ${variants[variant]}`}>
            {children}
        </span>
    );
}
