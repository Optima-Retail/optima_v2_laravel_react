import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ChevronDown, ClipboardList, Plus, Settings2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import { PageHeader } from '@/components/page/PageHeader';
import {
    RemoteDataTable,
    type RemoteDataColumnHelpers,
    type RemoteDataTableHandle,
} from '@/components/table/RemoteDataTable';
import { WorkOrderBulkStatusModal } from '@/components/work-orders/WorkOrderBulkStatusModal';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { numberingPatternsService, workOrdersService } from '@/services';
import { useToastStore } from '@/stores/toastStore';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorColorBadge,
    tabulatorDeleteButton,
    tabulatorEditLink,
    tabulatorPdfLink,
    tabulatorTextLink,
} from '@/support/tabulator';
import { formatDateTime } from '@/support/datetime';
import type { WorkOrderListItem } from '@/support/types/domain/work-order';
import type { WorkOrderStatusOption } from '@/support/types/domain/work-order-status';

type WorkOrdersIndexProps = {
    filters: {
        search: string;
        pending: string;
        created_from: string;
        created_to: string;
        sort: string;
        direction: string;
        per_page: string;
    };
    statusOptions: WorkOrderStatusOption[];
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
        configure_pattern: boolean;
    };
};

function subjectLabel(row: WorkOrderListItem, empty: string): string {
    const code = row.work_order_num?.trim() || row.code?.trim();
    const subject = row.subject?.trim();

    if (code && subject) {
        return `${code} - ${subject}`;
    }

    return code || subject || String(row.id) || empty;
}

function formatAmount(value: number | null | undefined, locale: string, empty: string): string {
    if (value === null || value === undefined) {
        return empty;
    }

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

function formatPercent(value: number | null | undefined, locale: string, empty: string): string {
    if (value === null || value === undefined) {
        return empty;
    }

    return `${new Intl.NumberFormat(locale, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(value)} %`;
}

function formatTableDate(value: string | null | undefined, locale: string, empty: string): string {
    return formatDateTime(value, locale) || empty;
}

export default function WorkOrdersIndex({ filters, statusOptions, can }: WorkOrdersIndexProps) {
    const { t, i18n } = useTranslation();
    const pushToast = useToastStore((state) => state.push);
    const tableRef = useRef<RemoteDataTableHandle>(null);
    const canRef = useRef(can);
    const actionsMenuRef = useRef<HTMLDivElement>(null);
    const empty = t('common.emDash');
    const noDate = t('establishments.documentColumns.noDate');
    const [selectedRows, setSelectedRows] = useState<WorkOrderListItem[]>([]);
    const [actionsOpen, setActionsOpen] = useState(false);
    const [bulkStatusOpen, setBulkStatusOpen] = useState(false);
    const [bulkStatusProcessing, setBulkStatusProcessing] = useState(false);

    useEffect(() => {
        canRef.current = can;
    }, [can]);

    useEffect(() => {
        if (!actionsOpen) {
            return;
        }

        function onPointerDown(event: MouseEvent) {
            if (!actionsMenuRef.current?.contains(event.target as Node)) {
                setActionsOpen(false);
            }
        }

        document.addEventListener('mousedown', onPointerDown);

        return () => document.removeEventListener('mousedown', onPointerDown);
    }, [actionsOpen]);

    const initialFilterValues = useMemo(
        () => ({
            search: filters.search,
            pending: filters.pending || '1',
            created_from: filters.created_from,
            created_to: filters.created_to,
        }),
        [filters.created_from, filters.created_to, filters.pending, filters.search],
    );

    const selectionOptions = useMemo(
        () =>
            can.update
                ? {
                      selectableRows: true,
                      rowHeader: {
                          formatter: 'rowSelection' as const,
                          titleFormatter: 'rowSelection' as const,
                          headerSort: false,
                          resizable: false,
                          frozen: true,
                          width: 44,
                          minWidth: 44,
                      },
                  }
                : undefined,
        [can.update],
    );

    function openBulkStatus() {
        setActionsOpen(false);

        if (selectedRows.length === 0) {
            pushToast(t('workOrders.bulkStatusNeedSelection'), 'error');

            return;
        }

        setBulkStatusOpen(true);
    }

    async function submitBulkStatus(payload: { statusId: number; justification: string }) {
        setBulkStatusProcessing(true);

        try {
            const result = await workOrdersService.bulkStatus({
                ids: selectedRows.map((row) => row.id),
                status_id: payload.statusId,
                status_justification: payload.justification || null,
            });

            setBulkStatusOpen(false);
            setSelectedRows([]);
            tableRef.current?.getTable()?.deselectRow();
            tableRef.current?.replaceData();

            if (result.failed.length === 0) {
                pushToast(t('workOrders.bulkStatusSuccess', { count: result.updated }), 'success');
            } else if (result.updated > 0) {
                pushToast(
                    t('workOrders.bulkStatusPartial', {
                        updated: result.updated,
                        failed: result.failed.length,
                    }),
                    'error',
                );
            } else {
                pushToast(t('workOrders.bulkStatusFailed'), 'error');
            }
        } catch {
            pushToast(t('workOrders.bulkStatusFailed'), 'error');
        } finally {
            setBulkStatusProcessing(false);
        }
    }

    function buildColumns({ titleFormatter, getTable }: RemoteDataColumnHelpers): ColumnDefinition[] {
        return [
            {
                title: t('establishments.documentColumns.subject'),
                field: 'subject',
                minWidth: 220,
                widthGrow: 3,
                headerSort: true,
                cssClass: 'cell-strong',
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as WorkOrderListItem;
                    const label = subjectLabel(row, empty);

                    if (!canRef.current.update) {
                        return label;
                    }

                    return tabulatorTextLink(workOrdersService.editPath(row.id), label);
                },
            },
            {
                title: t('workOrders.establishment'),
                field: 'establishment_name',
                minWidth: 160,
                widthGrow: 1,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || empty,
            },
            {
                title: t('establishments.documentColumns.type'),
                field: 'type_name',
                minWidth: 140,
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as WorkOrderListItem;
                    const name = row.type_name;

                    if (!name) {
                        return `<span class="text-ink-muted">${empty}</span>`;
                    }

                    return tabulatorColorBadge(name, row.type_color || '#94a3b8');
                },
            },
            {
                title: t('establishments.documentColumns.status'),
                field: 'status_name',
                minWidth: 150,
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as WorkOrderListItem;
                    const name = row.status_name;

                    if (!name) {
                        return `<span class="text-ink-muted">${empty}</span>`;
                    }

                    return tabulatorColorBadge(name, row.status_color || '#94a3b8');
                },
            },
            {
                title: t('establishments.documentColumns.createdAt'),
                field: 'created_at',
                minWidth: 150,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) =>
                    formatTableDate(cell.getValue() as string | null, i18n.language, noDate),
            },
            {
                title: t('establishments.documentColumns.interventionAt'),
                field: 'intervention_at',
                minWidth: 150,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) =>
                    formatTableDate(cell.getValue() as string | null, i18n.language, noDate),
            },
            {
                title: t('establishments.documentColumns.totalCost'),
                field: 'cost_amount',
                minWidth: 120,
                headerSort: false,
                hozAlign: 'right',
                headerHozAlign: 'right',
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as WorkOrderListItem;

                    return formatAmount(row.cost_amount, i18n.language, empty);
                },
            },
            {
                title: t('establishments.documentColumns.totalAmount'),
                field: 'total_euros',
                minWidth: 120,
                headerSort: false,
                hozAlign: 'right',
                headerHozAlign: 'right',
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as WorkOrderListItem;

                    return formatAmount(row.total_euros, i18n.language, empty);
                },
            },
            {
                title: t('establishments.documentColumns.margin'),
                field: 'margin_percentage',
                minWidth: 110,
                headerSort: false,
                hozAlign: 'right',
                headerHozAlign: 'right',
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as WorkOrderListItem;
                    const value = row.margin_percentage;
                    const label = formatPercent(value, i18n.language, empty);

                    if (value === null || value === undefined) {
                        return `<span class="text-ink-muted">${label}</span>`;
                    }

                    const tone = value > 0 ? 'text-success' : value < 0 ? 'text-danger' : 'text-ink';

                    return `<span class="${tone} font-semibold tabular-nums">${label}</span>`;
                },
            },
            {
                title: t('common.actions'),
                field: 'actions',
                minWidth: 148,
                width: 148,
                widthGrow: 0,
                widthShrink: 0,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const workOrder = cell.getRow().getData() as WorkOrderListItem;
                    const parts: string[] = [];
                    const name = subjectLabel(workOrder, String(workOrder.id));

                    parts.push(
                        tabulatorPdfLink(
                            workOrdersService.pdfPath(workOrder.id),
                            t('workOrders.downloadPdf', { name }),
                        ),
                    );

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                workOrdersService.editPath(workOrder.id),
                                t('common.editItem', { name }),
                            ),
                        );
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
                    const workOrder = cell.getRow().getData() as WorkOrderListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('workOrders.resource') }),
                        message: t('common.deleteMessage', {
                            name: subjectLabel(workOrder, String(workOrder.id)),
                        }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    workOrdersService.destroy(workOrder.id, {
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
        <AppLayout title={t('workOrders.title')}>
            <Head title={t('workOrders.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('workOrders.title')}
                    description={t('workOrders.descriptionPage')}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            {can.update ? (
                                <div className="relative" ref={actionsMenuRef}>
                                    <button
                                        type="button"
                                        className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-line bg-surface px-3 text-sm font-semibold text-ink transition-colors hover:bg-canvas"
                                        aria-expanded={actionsOpen}
                                        aria-haspopup="menu"
                                        onClick={() => setActionsOpen((open) => !open)}
                                    >
                                        {t('common.actions')}
                                        <ChevronDown className="size-3.5" aria-hidden />
                                    </button>
                                    {actionsOpen ? (
                                        <div
                                            role="menu"
                                            className="absolute right-0 z-20 mt-1 min-w-48 overflow-hidden rounded-lg border border-line bg-surface py-1 shadow-lg"
                                        >
                                            <button
                                                type="button"
                                                role="menuitem"
                                                className="flex w-full items-center px-3 py-2 text-left text-sm text-ink transition-colors hover:bg-canvas"
                                                onClick={openBulkStatus}
                                            >
                                                {t('workOrders.bulkStatusAction')}
                                            </button>
                                        </div>
                                    ) : null}
                                </div>
                            ) : null}
                            {can.configure_pattern ? (
                                <Link
                                    href={numberingPatternsService.indexPath}
                                    className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-line bg-surface px-3 text-sm font-semibold text-ink transition-colors hover:bg-canvas"
                                >
                                    <Settings2 className="size-3.5" aria-hidden />
                                    {t('workOrders.configurePattern')}
                                </Link>
                            ) : null}
                            {can.create ? (
                                <Link
                                    href={workOrdersService.createPath}
                                    className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                                >
                                    <Plus className="size-3.5" aria-hidden />
                                    {t('workOrders.new')}
                                </Link>
                            ) : null}
                        </div>
                    }
                />

                <RemoteDataTable<WorkOrderListItem>
                    ref={tableRef}
                    ajaxURL={workOrdersService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'id',
                        dir: filters.direction === 'asc' ? 'asc' : 'desc',
                    }}
                    pageSize={Number(filters.per_page) || 25}
                    initialFilters={initialFilterValues}
                    onRowSelectionChanged={setSelectedRows}
                    options={selectionOptions}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('workOrders.searchPlaceholder'),
                        },
                        {
                            type: 'select',
                            name: 'pending',
                            label: t('workOrders.pending'),
                            emptyLabel: t('common.all'),
                            options: [
                                { value: '1', label: t('workOrders.pendingOpen') },
                                { value: '0', label: t('workOrders.pendingClosed') },
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
                    savedFiltersPageKey="work_orders"
                    syncUrlBase={workOrdersService.indexPath}
                    emptyIcon={<ClipboardList className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('workOrders.resourcePlural') })}
                    deps={[i18n.language, can.update]}
                />
            </div>

            <WorkOrderBulkStatusModal
                open={bulkStatusOpen}
                selectedCount={selectedRows.length}
                statusOptions={statusOptions}
                processing={bulkStatusProcessing}
                onClose={() => setBulkStatusOpen(false)}
                onConfirm={(payload) => {
                    void submitBulkStatus(payload);
                }}
            />
        </AppLayout>
    );
}
