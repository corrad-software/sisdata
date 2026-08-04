import { forwardRef } from 'react';
import type { InputHTMLAttributes } from 'react';

export const TextInput = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(function TextInput(
    { className, ...props },
    ref,
) {
    return (
        <input
            ref={ref}
            className={`border-otl-gray-300 focus:border-primary-600 focus:ring-fr-primary rounded-md shadow-sm text-body-sm ${className ?? ''}`}
            {...props}
        />
    );
});
