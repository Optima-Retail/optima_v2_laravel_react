import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/support/cn';

export type BadgeVariant = 'success' | 'neutral' | 'danger' | 'warning' | 'brand';

const variantClassName: Record<BadgeVariant, string> = {
    success: 'bg-success/10 text-success',
    neutral: 'border border-line bg-canvas text-ink-muted',
    danger: 'bg-danger/10 text-danger',
    warning: 'bg-amber-100 text-amber-800',
    brand: 'bg-brand-soft text-brand',
};

type BadgeProps = {
    children: ReactNode;
    variant?: BadgeVariant;
    className?: string;
};

export function Badge({ children, variant = 'neutral', className }: BadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-semibold',
                variantClassName[variant],
                className,
            )}
        >
            {children}
        </span>
    );
}

type StatusBadgeProps = {
    active: boolean;
    activeLabel?: string;
    inactiveLabel?: string;
    className?: string;
};

/** Active / inactive status pill (tables, detail headers, etc.). */
export function StatusBadge({ active, activeLabel, inactiveLabel, className }: StatusBadgeProps) {
    const { t } = useTranslation();

    return (
        <Badge variant={active ? 'success' : 'neutral'} className={className}>
            {active ? (activeLabel ?? t('common.active')) : (inactiveLabel ?? t('common.inactive'))}
        </Badge>
    );
}

export function badgeVariantForRelationshipStatus(status: string): BadgeVariant {
    switch (status) {
        case 'active':
            return 'success';
        case 'prospect':
            return 'brand';
        case 'blocked':
            return 'danger';
        case 'inactive':
        case 'archived':
        default:
            return 'neutral';
    }
}
