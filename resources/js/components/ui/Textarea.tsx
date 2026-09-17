import type { TextareaHTMLAttributes } from 'react';
import { cn } from '@/support/cn';

type TextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement> & {
    invalid?: boolean;
};

export function Textarea({ className, invalid = false, rows = 3, ...props }: TextareaProps) {
    return (
        <textarea
            rows={rows}
            className={cn(
                'w-full rounded-lg border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition',
                'placeholder:text-ink-muted/70',
                'focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20',
                'disabled:cursor-not-allowed disabled:bg-canvas disabled:text-ink-muted disabled:opacity-80',
                invalid ? 'border-danger focus:border-danger focus:ring-danger/20' : 'border-line',
                className,
            )}
            {...props}
        />
    );
}
