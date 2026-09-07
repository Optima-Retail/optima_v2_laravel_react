import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Hash, Plus } from 'lucide-react';
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
import { seriesService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { SeriesListItem } from '@/support/types/domain';

type SeriesIndexProps = {
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

export default function SeriesIndex({ filters, can }: SeriesIndexProps) {
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
                title: t('series.key'),
                field: 'key',
                minWidth: 140,
                headerSort: true,
                titleFormatter,
            },
            {
                title: t('common.color'),
                field: 'color',
                minWidth: 140,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const item = cell.getRow().getData() as SeriesListItem;

                    return `<span class="inline-flex items-center gap-2"><span class="inline-block size-4 rounded border border-line" style="background-color: ${item.color}"></span>${item.color}</span>`;
                },
            },
            {
                title: t('series.selectable'),
                field: 'is_selectable',
                minWidth: 120,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) =>
                    cell.getValue() ? t('common.yes') : t('common.no'),
            },
            {
                title: t('series.creditNote'),
                field: 'credit_note_series_id',
                minWidth: 140,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const item = cell.getRow().getData() as SeriesListItem;

                    return item.credit_note_series_key ?? t('common.emDash');
                },
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: 104,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const item = cell.getRow().getData() as SeriesListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                seriesService.editPath(item.id),
                                t('common.editItem', { name: item.key }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(tabulatorDeleteButton(t('common.deleteItem', { name: item.key })));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const item = cell.getRow().getData() as SeriesListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('series.resource') }),
                        message: t('common.deleteMessage', { name: item.key }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    seriesService.destroy(item.id, {
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
        <AppLayout title={t('series.title')}>
            <Head title={t('series.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('series.title')}
                    description={t('series.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={seriesService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.newItem', { resource: t('series.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<SeriesListItem>
                    ref={tableRef}
                    ajaxURL={seriesService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'key',
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
                            placeholder: t('series.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={seriesService.indexPath}
                    emptyIcon={<Hash className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('series.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
