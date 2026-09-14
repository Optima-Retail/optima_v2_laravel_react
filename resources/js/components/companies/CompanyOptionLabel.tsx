import { Building2 } from 'lucide-react';
import { cn } from '@/support/cn';

type CompanyMarkSize = 'xs' | 'sm' | 'md';

const sizeClass: Record<CompanyMarkSize, string> = {
    xs: 'size-5',
    sm: 'size-7',
    md: 'size-9',
};

const iconClass: Record<CompanyMarkSize, string> = {
    xs: 'size-3',
    sm: 'size-3.5',
    md: 'size-5',
};

type CompanyMarkProps = {
    name: string;
    logoUrl?: string | null;
    size?: CompanyMarkSize;
    className?: string;
};

/** Compact company logo (or building fallback) for selects, chips, and switchers. */
export function CompanyMark({ name, logoUrl, size = 'sm', className }: CompanyMarkProps) {
    if (logoUrl) {
        return (
            <img
                src={logoUrl}
                alt=""
                title={name}
                className={cn(sizeClass[size], 'shrink-0 rounded-md object-contain', className)}
            />
        );
    }

    return (
        <span
            className={cn(
                sizeClass[size],
                'inline-flex shrink-0 items-center justify-center rounded-md border border-line bg-canvas text-ink-muted',
                className,
            )}
            aria-hidden
        >
            <Building2 className={iconClass[size]} />
        </span>
    );
}

type CompanyOptionLabelProps = {
    name: string;
    logoUrl?: string | null;
    size?: CompanyMarkSize;
    className?: string;
};

/** Logo + name row used in company selects and lists. */
export function CompanyOptionLabel({ name, logoUrl, size = 'md', className }: CompanyOptionLabelProps) {
    return (
        <span className={cn('inline-flex min-w-0 items-center gap-2.5', className)}>
            <CompanyMark name={name} logoUrl={logoUrl} size={size} />
            <span className="truncate">{name}</span>
        </span>
    );
}
