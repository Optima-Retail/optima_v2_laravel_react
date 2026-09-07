import type { LabelHTMLAttributes, PropsWithChildren } from 'react';
import { cn } from '@/support/cn';

export function Label({
    children,
    className,
    ...props
}: PropsWithChildren<LabelHTMLAttributes<HTMLLabelElement>>) {
    return (
        <label className={cn('mb-1.5 block text-sm font-semibold text-ink', className)} {...props}>
            {children}
        </label>
    );
}
