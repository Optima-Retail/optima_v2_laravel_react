import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Package, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import { PageHeader } from '@/components/page/PageHeader';
import {
    RemoteDataTable,
    type RemoteDataColumnHelpers,
    type RemoteDataTableHandle,
} from '@/components/table/RemoteDataTable';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { articlesService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
    tabulatorStatusBadge,
} from '@/support/tabulator';
import type { ArticleListItem } from '@/support/types/domain/article';

type ArticlesIndexProps = {
    filters: {
        search: string;
        sort: string;
        direction: string;
        per_page: string;
    };
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
};

export default function ArticlesIndex({ filters, can }: ArticlesIndexProps) {
    const { t, i18n } = useTranslation();
    const tableRef = useRef<RemoteDataTableHandle>(null);
    const canRef = useRef(can);

    useEffect(() => {
        canRef.current = can;
    }, [can]);

    function buildColumns({ titleFormatter, getTable }: RemoteDataColumnHelpers): ColumnDefinition[] {
        return [
            {
                title: t('common.id'),
                field: 'id',
                width: 88,
                headerSort: true,
                titleFormatter,
            },
            {
                title: t('articles.code'),
                field: 'code',
                width: 140,
                headerSort: true,
                titleFormatter,
            },
            {
                title: t('common.name'),
                field: 'name',
                minWidth: 220,
                headerSort: false,
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as ArticleListItem;

                    return row.name ?? '—';
                },
            },
            {
                title: t('articles.isDeletable'),
                field: 'is_deletable',
                width: 130,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) =>
                    tabulatorStatusBadge(Boolean(cell.getValue()), t('common.yes'), t('common.no')),
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: 104,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const article = cell.getRow().getData() as ArticleListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                articlesService.editPath(article.id),
                                t('common.editItem', { name: article.code }),
                            ),
                        );
                    }

                    if (canRef.current.delete && article.is_deletable) {
                        parts.push(tabulatorDeleteButton(t('common.deleteItem', { name: article.code })));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const article = cell.getRow().getData() as ArticleListItem;

                    if (!article.is_deletable) {
                        return;
                    }

                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('articles.resource') }),
                        message: t('common.deleteMessage', { name: article.code }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    articlesService.destroy(article.id, {
                        preserveScroll: true,
                        onSuccess: () => {
                            getTable()?.replaceData();
                        },
                    });
                },
            },
        ];
    }

    return (
        <AppLayout title={t('nav.articles')}>
            <Head title={t('nav.articles')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('nav.articles')}
                    description={t('articles.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={articlesService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('articles.new')}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<ArticleListItem>
                    ref={tableRef}
                    ajaxURL={articlesService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'code',
                        dir: filters.direction === 'desc' ? 'desc' : 'asc',
                    }}
                    pageSize={Number(filters.per_page) || 12}
                    initialFilters={{
                        search: filters.search,
                    }}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('articles.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={articlesService.indexPath}
                    emptyIcon={<Package className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('articles.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
