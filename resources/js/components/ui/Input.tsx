import type { InputHTMLAttributes } from 'react';
import { cn } from '@/support/cn';

type InputProps = InputHTMLAttributes<HTMLInputElement> & {
    invalid?: boolean;
};

export function Input({ className, invalid = false, ...props }: InputProps) {
    return (
        <input
            className={cn(
                'h-8 w-full rounded-lg border bg-surface px-3 text-sm text-ink shadow-sm transition',
                'placeholder:text-ink-muted/70',
                'focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20',
                invalid ? 'border-danger focus:border-danger focus:ring-danger/20' : 'border-line',
                className,
            )}
            {...props}
        />
    );
}
