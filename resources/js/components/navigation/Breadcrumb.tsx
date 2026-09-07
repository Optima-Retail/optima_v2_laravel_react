import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/support/cn';

export type BreadcrumbItem = {
    label: string;
    href?: string;
};

type BreadcrumbProps = {
    items: BreadcrumbItem[];
    className?: string;
};

export function Breadcrumb({ items, className }: BreadcrumbProps) {
    const { t } = useTranslation();

    if (items.length === 0) {
        return null;
    }

    return (
        <nav aria-label={t('nav.breadcrumb')} className={cn('flex items-center gap-1 text-sm', className)}>
            {items.map((item, index) => {
                const isLast = index === items.length - 1;

                return (
                    <span key={`${item.label}-${index}`} className="inline-flex items-center gap-1">
                        {index > 0 ? <ChevronRight className="size-3.5 text-ink-muted" aria-hidden /> : null}
                        {item.href && !isLast ? (
                            <Link href={item.href} className="font-medium text-ink-muted transition-colors hover:text-brand">
                                {item.label}
                            </Link>
                        ) : (
                            <span className={cn('font-medium', isLast ? 'text-ink' : 'text-ink-muted')}>
                                {item.label}
                            </span>
                        )}
                    </span>
                );
            })}
        </nav>
    );
}
