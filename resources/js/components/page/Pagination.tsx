import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Select } from '@/components/ui/Select';
import {
    buildPageWindow,
    resolveAdjacentUrl,
    resolvePageUrl,
} from '@/helpers/pagination';
import { cn } from '@/support/cn';
import type { Paginated } from '@/support/pagination';

export const PER_PAGE_OPTIONS = ['10', '12', '25', '50', '100'] as const;

type PaginationProps<T> = {
    paginator: Paginated<T>;
    onVisit: (url: string) => void;
    perPage?: string;
    onPerPageChange?: (perPage: string) => void;
    className?: string;
};

export function Pagination<T>({
    paginator,
    onVisit,
    perPage,
    onPerPageChange,
    className,
}: PaginationProps<T>) {
    const { t } = useTranslation();
    const total = paginator.total ?? paginator.data.length;

    if (total === 0) {
        return null;
    }

    const currentPage = paginator.current_page ?? 1;
    const lastPage = paginator.last_page;
    const from = paginator.from ?? (paginator.data.length > 0 ? 1 : 0);
    const to = paginator.to ?? paginator.data.length;
    const currentPerPage = perPage ?? String(paginator.per_page ?? 12);
    const pageItems = buildPageWindow(currentPage, lastPage);
    const prevUrl = resolveAdjacentUrl(paginator, 'prev');
    const nextUrl = resolveAdjacentUrl(paginator, 'next');

    return (
        <nav
            className={cn(
                'rounded-2xl border border-line bg-surface p-3 sm:p-4',
                className,
            )}
            aria-label={t('pagination.label')}
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-wrap items-center gap-3">
                    <p className="text-sm text-ink-muted">
                        {t('pagination.showing', { from, to, total })}
                    </p>

                    {onPerPageChange ? (
                        <label className="inline-flex items-center gap-2 text-sm text-ink-muted">
                            <span className="whitespace-nowrap">{t('pagination.rows')}</span>
                            <Select
                                value={currentPerPage}
                                onChange={(event) => onPerPageChange(event.target.value)}
                                className="h-8 w-auto min-w-16 py-1"
                                aria-label={t('pagination.rowsPerPage')}
                            >
                                {PER_PAGE_OPTIONS.map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </Select>
                        </label>
                    ) : null}
                </div>

                {lastPage > 1 ? (
                    <div className="flex flex-wrap items-center justify-end gap-1">
                        <button
                            type="button"
                            disabled={!prevUrl}
                            onClick={() => prevUrl && onVisit(prevUrl)}
                            className={cn(
                                'inline-flex size-8 items-center justify-center rounded-md border border-line bg-canvas text-ink-muted transition-colors',
                                'hover:text-ink disabled:pointer-events-none disabled:opacity-40',
                            )}
                            aria-label={t('pagination.previous')}
                        >
                            <ChevronLeft className="size-4" aria-hidden />
                        </button>

                        {pageItems.map((item, index) => {
                            if (item === 'ellipsis') {
                                return (
                                    <span
                                        key={`ellipsis-${index}`}
                                        className="inline-flex min-w-7 select-none items-center justify-center px-1 text-sm text-ink-muted"
                                        aria-hidden
                                    >
                                        …
                                    </span>
                                );
                            }

                            const url = resolvePageUrl(paginator, item);
                            const active = item === currentPage;

                            return (
                                <button
                                    key={item}
                                    type="button"
                                    disabled={!url}
                                    onClick={() => {
                                        if (!active && url) {
                                            onVisit(url);
                                        }
                                    }}
                                    aria-current={active ? 'page' : undefined}
                                    aria-label={t('pagination.page', { page: item })}
                                    className={cn(
                                        'inline-flex min-w-8 items-center justify-center rounded-md px-2 py-1.5 text-sm font-semibold transition-colors',
                                        active
                                            ? 'pointer-events-none bg-brand text-white'
                                            : 'border border-line bg-canvas text-ink-muted hover:text-ink disabled:opacity-40',
                                    )}
                                >
                                    {item}
                                </button>
                            );
                        })}

                        <button
                            type="button"
                            disabled={!nextUrl}
                            onClick={() => nextUrl && onVisit(nextUrl)}
                            className={cn(
                                'inline-flex size-8 items-center justify-center rounded-md border border-line bg-canvas text-ink-muted transition-colors',
                                'hover:text-ink disabled:pointer-events-none disabled:opacity-40',
                            )}
                            aria-label={t('pagination.next')}
                        >
                            <ChevronRight className="size-4" aria-hidden />
                        </button>
                    </div>
                ) : null}
            </div>
        </nav>
    );
}
