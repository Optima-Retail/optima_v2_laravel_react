import type { ButtonHTMLAttributes, PropsWithChildren } from 'react';
import { cn } from '@/support/cn';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
type Size = 'sm' | 'md' | 'lg';

type ButtonProps = PropsWithChildren<
    ButtonHTMLAttributes<HTMLButtonElement> & {
        variant?: Variant;
        size?: Size;
        loading?: boolean;
    }
>;

const variants: Record<Variant, string> = {
    primary: 'bg-brand text-white hover:bg-brand-strong',
    secondary: 'bg-surface text-ink border border-line hover:border-brand/40 hover:text-brand',
    ghost: 'bg-transparent text-ink-muted hover:text-ink hover:bg-brand-soft',
    danger: 'bg-danger text-white hover:opacity-90',
};

const sizes: Record<Size, string> = {
    sm: 'h-7 px-2.5 text-xs',
    md: 'h-8 px-3 text-sm',
    lg: 'h-9 px-3.5 text-sm',
};

export function Button({
    children,
    className,
    variant = 'primary',
    size = 'md',
    loading = false,
    disabled,
    type = 'button',
    ...props
}: ButtonProps) {
    return (
        <button
            type={type}
            disabled={disabled || loading}
            className={cn(
                'inline-flex items-center justify-center gap-1.5 rounded-lg font-semibold transition-colors duration-200',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/40 focus-visible:ring-offset-2',
                'disabled:cursor-not-allowed disabled:opacity-60',
                variants[variant],
                sizes[size],
                className,
            )}
            {...props}
        >
            {loading ? (
                <span className="size-3.5 animate-spin rounded-full border-2 border-white/40 border-t-white" />
            ) : null}
            {children}
        </button>
    );
}
