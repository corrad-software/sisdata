import Link from 'next/link';
import type { AnchorHTMLAttributes, ButtonHTMLAttributes, ReactNode } from 'react';

type Variant = 'primary' | 'secondary' | 'danger' | 'outline';
type Size = 'sm' | 'md' | 'lg';

const variants: Record<Variant, string> = {
    primary:
        'bg-primary-600 text-white border border-primary-600 shadow-button hover:bg-primary-700 hover:border-primary-700 focus-visible:ring-fr-primary disabled:bg-bg-primary-disabled disabled:border-bg-primary-disabled',
    secondary:
        'bg-bg-white text-txt-black-900 border border-otl-gray-300 shadow-button hover:bg-bg-washed focus-visible:ring-fr-primary disabled:opacity-50',
    danger: 'bg-danger-600 text-white border border-danger-600 shadow-button hover:bg-danger-700 hover:border-danger-700 focus-visible:ring-fr-danger disabled:opacity-50',
    outline:
        'bg-transparent text-primary-600 border border-primary-600 hover:bg-primary-50 focus-visible:ring-fr-primary disabled:opacity-50',
};

const sizes: Record<Size, string> = {
    sm: 'px-3 py-1.5 text-body-xs gap-1.5',
    md: 'px-4 py-2 text-body-sm gap-2',
    lg: 'px-5 py-2.5 text-body-md gap-2',
};

const base =
    'inline-flex items-center justify-center font-medium rounded-md transition ease-in-out duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed';

function classes(variant: Variant, size: Size, className?: string) {
    return [base, variants[variant], sizes[size], className].filter(Boolean).join(' ');
}

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    size?: Size;
}

export function Button({ variant = 'primary', size = 'md', className, type = 'button', children, ...props }: ButtonProps) {
    return (
        <button type={type} className={classes(variant, size, className)} {...props}>
            {children}
        </button>
    );
}

interface LinkButtonProps extends AnchorHTMLAttributes<HTMLAnchorElement> {
    variant?: Variant;
    size?: Size;
    href: string;
    children?: ReactNode;
}

/**
 * Button-styled plain `<a>` for full-page navigations that must leave the
 * Next.js router (e.g. backend redirects like `/auth/oidc/redirect`), where
 * `next/link`'s client-side routing/prefetching would be wrong.
 */
export function AnchorButton({ variant = 'primary', size = 'md', className, href, children, ...props }: LinkButtonProps) {
    return (
        <a href={href} className={classes(variant, size, className)} {...props}>
            {children}
        </a>
    );
}

export function LinkButton({ variant = 'primary', size = 'md', className, href, children, ...props }: LinkButtonProps) {
    return (
        <Link href={href} className={classes(variant, size, className)} {...props}>
            {children}
        </Link>
    );
}
