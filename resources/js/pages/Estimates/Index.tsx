import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { FileSpreadsheet, Plus, Settings2 } from 'lucide-react';
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
import { estimatesService, numberingPatternsService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorColorBadge,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { WorkOrderListItem } from '@/support/types/domain/work-order';

type EstimatesIndexProps = {
    filters: {
        search: string;
        pending: string;
        created_from: string;
        created_to: string;
        sort: string;
        direction: string;
        per_page: string;
    };
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
        configure_pattern: boolean;
    };
};

export default function EstimatesIndex({ filters, can }: EstimatesIndexProps) {
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
                width: 72,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
            },
            {
                title: t('common.code'),
                field: 'code',
                minWidth: 110,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('estimates.subject'),
                field: 'subject',
                minWidth: 200,
                headerSort: true,
                cssClass: 'cell-strong',
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('estimates.establishment'),
                field: 'establishment_name',
                minWidth: 160,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('estimates.status'),
                field: 'status_name',
                minWidth: 160,
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as WorkOrderListItem;
                    const name = row.status_name;

                    if (!name) {
                        return `<span class="text-ink-muted">${t('common.emDash')}</span>`;
                    }

                    return tabulatorColorBadge(name, row.status_color || '#94a3b8');
                },
            },
            {
                title: t('estimates.responsibleUser'),
                field: 'responsible_user_name',
                minWidth: 140,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: 104,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const estimate = cell.getRow().getData() as WorkOrderListItem;
                    const parts: string[] = [];
                    const name = estimate.subject || estimate.code || estimate.id;

                    if (canRef.current.update) {
                        parts.push(tabulatorEditLink(estimatesService.editPath(estimate.id), t('common.editItem', { name })));
                    }

                    if (canRef.current.delete) {
                        parts.push(tabulatorDeleteButton(t('common.deleteItem', { name })));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const estimate = cell.getRow().getData() as WorkOrderListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('estimates.resource') }),
                        message: t('common.deleteMessage', {
                            name: estimate.subject || estimate.code || estimate.id,
                        }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    estimatesService.destroy(estimate.id, {
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
        <AppLayout title={t('estimates.title')}>
            <Head title={t('estimates.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('estimates.title')}
                    description={t('estimates.descriptionPage')}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            {can.configure_pattern ? (
                                <Link
                                    href={numberingPatternsService.indexPath}
                                    className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-line bg-surface px-3 text-sm font-semibold text-ink transition-colors hover:bg-canvas"
                                >
                                    <Settings2 className="size-3.5" aria-hidden />
                                    {t('estimates.configurePattern')}
                                </Link>
                            ) : null}
                            {can.create ? (
                                <Link
                                    href={estimatesService.createPath}
                                    className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                                >
                                    <Plus className="size-3.5" aria-hidden />
                                    {t('estimates.new')}
                                </Link>
                            ) : null}
                        </div>
                    }
                />

                <RemoteDataTable<WorkOrderListItem>
                    ref={tableRef}
                    ajaxURL={estimatesService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'id',
                        dir: filters.direction === 'asc' ? 'asc' : 'desc',
                    }}
                    pageSize={Number(filters.per_page) || 12}
                    initialFilters={{
                        search: filters.search,
                        pending: filters.pending || '1',
                        created_from: filters.created_from,
                        created_to: filters.created_to,
                    }}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('estimates.searchPlaceholder'),
                        },
                        {
                            type: 'select',
                            name: 'pending',
                            label: t('estimates.pending'),
                            emptyLabel: t('common.all'),
                            options: [
                                { value: '1', label: t('estimates.pendingOpen') },
                                { value: '0', label: t('estimates.pendingClosed') },
                            ],
                        },
                        {
                            type: 'date',
                            name: 'created_from',
                            label: t('filters.createdFrom'),
                        },
                        {
                            type: 'date',
                            name: 'created_to',
                            label: t('filters.createdTo'),
                        },
                    ]}
                    savedFiltersPageKey="estimates"
                    syncUrlBase={estimatesService.indexPath}
                    emptyIcon={<FileSpreadsheet className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('estimates.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
