import type { SelectHTMLAttributes } from 'react';
import { cn } from '@/support/cn';

type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & {
    invalid?: boolean;
};

export function Select({ className, invalid = false, children, ...props }: SelectProps) {
    return (
        <select
            className={cn(
                'h-8 w-full rounded-lg border bg-surface px-3 text-sm text-ink shadow-sm transition',
                'focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20',
                invalid ? 'border-danger focus:border-danger focus:ring-danger/20' : 'border-line',
                className,
            )}
            {...props}
        >
            {children}
        </select>
    );
}
