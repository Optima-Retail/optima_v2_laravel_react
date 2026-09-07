import { cn } from '@/support/cn';

type SpinnerSize = 'sm' | 'md' | 'lg';

const sizes: Record<SpinnerSize, string> = {
    sm: 'size-3.5 border-2',
    md: 'size-8 border-2',
    lg: 'size-10 border-[3px]',
};

type SpinnerProps = {
    className?: string;
    size?: SpinnerSize;
    label?: string;
};

export function Spinner({ className, size = 'md', label }: SpinnerProps) {
    return (
        <span
            role="status"
            aria-label={label}
            className={cn(
                'inline-block animate-spin rounded-full border-brand/25 border-t-brand',
                sizes[size],
                className,
            )}
        />
    );
}
