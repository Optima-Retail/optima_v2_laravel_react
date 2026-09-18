import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ChevronDown, FileSpreadsheet, Plus, Settings2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import { EstimateBulkStatusModal } from '@/components/estimates/EstimateBulkStatusModal';
import {
    EstablishmentDocumentTotals,
    type EstablishmentDocumentTotalsData,
} from '@/components/establishments/EstablishmentDocumentTotals';
import { PageHeader } from '@/components/page/PageHeader';
import {
    RemoteDataTable,
    type RemoteDataColumnHelpers,
    type RemoteDataTableHandle,
    type RemoteQueryState,
} from '@/components/table/RemoteDataTable';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { estimatesService, numberingPatternsService, workOrdersService } from '@/services';
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
    statusOptions: WorkOrderStatusOption[];
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
        configure_pattern: boolean;
    };
};

const EMPTY_TOTALS: EstablishmentDocumentTotalsData = {
    count: 0,
    total_amount: 0,
    cost_amount: 0,
    margin_percentage: 0,
};

function documentEditPath(row: WorkOrderListItem): string {
    return estimatesService.editPath(row.id);
}

function subjectLabel(row: WorkOrderListItem, empty: string): string {
    // Estimates index always shows the estimate number, even after confirm.
    const code = row.estimate_num?.trim() || row.code?.trim();
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

function normalizePendingFilter(value: string | undefined | null): string {
    if (value === '0' || value === '1' || value === 'all') {
        return value;
    }

    // Legacy empty select = all
    if (value === '') {
        return 'all';
    }

    return '1';
}

function totalsQueryFromFilters(filters: Record<string, string>): string {
    const params = new URLSearchParams();

    for (const key of ['search', 'created_from', 'created_to'] as const) {
        const value = filters[key]?.trim();

        if (value) {
            params.set(key, value);
        }
    }

    params.set('pending', normalizePendingFilter(filters.pending));

    return params.toString();
}

export default function EstimatesIndex({ filters, statusOptions, can }: EstimatesIndexProps) {
    const { t, i18n } = useTranslation();
    const pushToast = useToastStore((state) => state.push);
    const tableRef = useRef<RemoteDataTableHandle>(null);
    const canRef = useRef(can);
    const actionsMenuRef = useRef<HTMLDivElement>(null);
    const empty = t('common.emDash');
    const noDate = t('establishments.documentColumns.noDate');
    const [totals, setTotals] = useState<EstablishmentDocumentTotalsData>(EMPTY_TOTALS);
    const [selectedRows, setSelectedRows] = useState<WorkOrderListItem[]>([]);
    const [actionsOpen, setActionsOpen] = useState(false);
    const [bulkStatusOpen, setBulkStatusOpen] = useState(false);
    const [bulkStatusProcessing, setBulkStatusProcessing] = useState(false);
    const [totalsQuery, setTotalsQuery] = useState(() =>
        totalsQueryFromFilters({
            search: filters.search,
            pending: normalizePendingFilter(filters.pending),
            created_from: filters.created_from,
            created_to: filters.created_to,
        }),
    );

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

    useEffect(() => {
        const params = new URLSearchParams(totalsQuery);
        const search = (params.get('search') ?? '').trim();

        // Search aggregates cannot use indexes; skip totals while searching.
        if (search !== '') {
            setTotals(EMPTY_TOTALS);

            return;
        }

        const controller = new AbortController();

        void fetch(`${estimatesService.totalsPath}?${totalsQuery}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
            credentials: 'same-origin',
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                return (await response.json()) as EstablishmentDocumentTotalsData;
            })
            .then((payload) => {
                setTotals({
                    count: Number(payload.count ?? 0),
                    total_amount: Number(payload.total_amount ?? 0),
                    cost_amount: Number(payload.cost_amount ?? 0),
                    margin_percentage: Number(payload.margin_percentage ?? 0),
                });
            })
            .catch((error: unknown) => {
                if (error instanceof DOMException && error.name === 'AbortError') {
                    return;
                }

                setTotals(EMPTY_TOTALS);
            });

        return () => controller.abort();
    }, [totalsQuery]);

    const initialFilterValues = useMemo(
        () => ({
            search: filters.search,
            pending: normalizePendingFilter(filters.pending),
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

    function handleQueryChange(query: RemoteQueryState) {
        setTotalsQuery(
            totalsQueryFromFilters({
                search: query.search ?? '',
                pending: normalizePendingFilter(query.pending),
                created_from: query.created_from ?? '',
                created_to: query.created_to ?? '',
            }),
        );
    }

    function openBulkStatus() {
        setActionsOpen(false);

        if (selectedRows.length === 0) {
            pushToast(t('estimates.bulkStatusNeedSelection'), 'error');

            return;
        }

        setBulkStatusOpen(true);
    }

    async function submitBulkStatus(payload: { statusId: number; justification: string }) {
        setBulkStatusProcessing(true);

        try {
            const result = await estimatesService.bulkStatus({
                ids: selectedRows.map((row) => row.id),
                status_id: payload.statusId,
                status_justification: payload.justification || null,
            });

            setBulkStatusOpen(false);
            setSelectedRows([]);
            tableRef.current?.getTable()?.deselectRow();
            tableRef.current?.replaceData();
            setTotalsQuery(totalsQueryFromFilters(tableRef.current?.getFilters() ?? initialFilterValues));

            if (result.failed.length === 0) {
                pushToast(t('estimates.bulkStatusSuccess', { count: result.updated }), 'success');
            } else if (result.updated > 0) {
                pushToast(
                    t('estimates.bulkStatusPartial', {
                        updated: result.updated,
                        failed: result.failed.length,
                    }),
                    'error',
                );
            } else {
                pushToast(t('estimates.bulkStatusFailed'), 'error');
            }
        } catch {
            pushToast(t('estimates.bulkStatusFailed'), 'error');
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

                    return tabulatorTextLink(documentEditPath(row), label);
                },
            },
            {
                title: t('estimates.establishment'),
                field: 'establishment_name',
                minWidth: 160,
                widthGrow: 1,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || empty,
            },
            {
                title: t('estimates.convertedToWorkOrder'),
                field: 'is_work_order',
                minWidth: 120,
                headerSort: false,
                hozAlign: 'center',
                headerHozAlign: 'center',
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as WorkOrderListItem;

                    if (!row.is_work_order) {
                        return `<span class="text-ink-muted">${t('common.no')}</span>`;
                    }

                    const woCode = row.work_order_num?.trim();

                    if (woCode && canRef.current.update) {
                        return tabulatorTextLink(workOrdersService.editPath(row.id), woCode);
                    }

                    return woCode || t('common.yes');
                },
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
                title: t('establishments.documentColumns.expectedCloseAt'),
                field: 'expected_close_at',
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
                    const estimate = cell.getRow().getData() as WorkOrderListItem;
                    const parts: string[] = [];
                    const name = subjectLabel(estimate, String(estimate.id));

                    parts.push(
                        tabulatorPdfLink(
                            estimatesService.pdfPath(estimate.id),
                            t('estimates.downloadPdf', { name }),
                        ),
                    );

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                documentEditPath(estimate),
                                t('common.editItem', { name }),
                            ),
                        );
                    }

                    if (canRef.current.delete && estimate.stage === 'estimate' && !estimate.is_work_order) {
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
                            name: subjectLabel(estimate, String(estimate.id)),
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
                            setTotalsQuery(
                                totalsQueryFromFilters(tableRef.current?.getFilters() ?? initialFilterValues),
                            );
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
                                                {t('estimates.bulkStatusAction')}
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

                <EstablishmentDocumentTotals totals={totals} />

                <RemoteDataTable<WorkOrderListItem>
                    ref={tableRef}
                    ajaxURL={estimatesService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'id',
                        dir: filters.direction === 'asc' ? 'asc' : 'desc',
                    }}
                    pageSize={Number(filters.per_page) || 25}
                    initialFilters={initialFilterValues}
                    onQueryChange={handleQueryChange}
                    onRowSelectionChanged={setSelectedRows}
                    options={selectionOptions}
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
                            emptyLabel: null,
                            options: [
                                { value: 'all', label: t('common.all') },
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
                    deps={[i18n.language, can.update]}
                />
            </div>

            <EstimateBulkStatusModal
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
