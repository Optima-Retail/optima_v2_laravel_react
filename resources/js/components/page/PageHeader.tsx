import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/support/cn';

type PageHeaderProps = {
    title: string;
    /** @deprecated Kept for compatibility; no longer rendered. */
    eyebrow?: string;
    description?: ReactNode;
    actions?: ReactNode;
    backHref?: string;
    backLabel?: string;
    className?: string;
};

export function PageHeader({
    title,
    description,
    actions,
    backHref,
    backLabel,
    className,
}: PageHeaderProps) {
    const { t } = useTranslation();

    return (
        <header className={cn('space-y-3', className)}>
            {backHref ? (
                <Link
                    href={backHref}
                    className="inline-flex items-center gap-1.5 text-sm text-ink-muted transition-colors hover:text-ink"
                >
                    <ArrowLeft className="size-3.5 shrink-0" aria-hidden />
                    {backLabel ?? t('common.back')}
                </Link>
            ) : null}

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                    <h1 className="text-xl font-semibold tracking-tight text-ink">{title}</h1>
                    {description ? <p className="mt-1 text-sm text-ink-muted">{description}</p> : null}
                </div>

                {actions ? (
                    <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>
                ) : null}
            </div>
        </header>
    );
}
