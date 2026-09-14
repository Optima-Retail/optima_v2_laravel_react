import { useEffect, useRef } from 'react';
import { Link } from '@inertiajs/react';
import { ClipboardList, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import {
    EstablishmentDocumentTotals,
    type EstablishmentDocumentTotalsData,
} from '@/components/establishments/EstablishmentDocumentTotals';
import {
    RemoteDataTable,
    type RemoteDataColumnHelpers,
    type RemoteDataTableHandle,
} from '@/components/table/RemoteDataTable';
import { confirmAction } from '@/helpers/confirm';
import { estimatesService, workOrdersService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorColorBadge,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { WorkOrderListItem, WorkOrderStage } from '@/support/types/domain/work-order';

type EstablishmentDocumentsPanelProps = {
    establishmentId: number;
    stage: WorkOrderStage;
    totals: EstablishmentDocumentTotalsData;
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
};

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
    }).format(value)}%`;
}

function formatDate(value: string | null | undefined, locale: string, empty: string): string {
    if (!value) {
        return empty;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return empty;
    }

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(date);
}

function subjectLabel(row: WorkOrderListItem, empty: string): string {
    const code = row.code?.trim();
    const subject = row.subject?.trim();

    if (code && subject) {
        return `${code} - ${subject}`;
    }

    return code || subject || String(row.id) || empty;
}

export function EstablishmentDocumentsPanel({
    establishmentId,
    stage,
    totals,
    can,
}: EstablishmentDocumentsPanelProps) {
    const { t, i18n } = useTranslation();
    const tableRef = useRef<RemoteDataTableHandle>(null);
    const canRef = useRef(can);
    const isEstimate = stage === 'estimate';
    const service = isEstimate ? estimatesService : workOrdersService;
    const resourceKey = isEstimate ? 'estimates' : 'workOrders';
    const empty = t('common.emDash');
    const noDate = t('establishments.documentColumns.noDate');

    useEffect(() => {
        canRef.current = can;
    }, [can]);

    function buildColumns({ titleFormatter, getTable }: RemoteDataColumnHelpers): ColumnDefinition[] {
        const subjectColumn: ColumnDefinition = {
            title: t('establishments.documentColumns.subject'),
            field: 'subject',
            minWidth: 220,
            headerSort: true,
            cssClass: 'cell-strong',
            titleFormatter,
            formatter: (cell: CellComponent) => {
                const row = cell.getRow().getData() as WorkOrderListItem;
                const label = subjectLabel(row, empty);

                if (!canRef.current.update) {
                    return label;
                }

                return `<a href="${service.editPath(row.id)}" class="text-brand hover:underline">${label}</a>`;
            },
        };

        const statusColumn: ColumnDefinition = {
            title: t('establishments.documentColumns.status'),
            field: 'status_name',
            minWidth: 140,
            headerSort: false,
            formatter: (cell: CellComponent) => {
                const row = cell.getRow().getData() as WorkOrderListItem;
                const name = row.status_name;

                if (!name) {
                    return `<span class="text-ink-muted">${empty}</span>`;
                }

                return tabulatorColorBadge(name, row.status_color || '#94a3b8');
            },
        };

        const typeColumn: ColumnDefinition = {
            title: t('establishments.documentColumns.type'),
            field: 'type_name',
            minWidth: 130,
            headerSort: false,
            formatter: (cell: CellComponent) => {
                const row = cell.getRow().getData() as WorkOrderListItem;
                const name = row.type_name;

                if (!name) {
                    return `<span class="text-ink-muted">${empty}</span>`;
                }

                return tabulatorColorBadge(name, row.type_color || '#94a3b8');
            },
        };

        const createdColumn: ColumnDefinition = {
            title: t('establishments.documentColumns.createdAt'),
            field: 'created_at',
            minWidth: 140,
            headerSort: true,
            cssClass: 'cell-muted',
            titleFormatter,
            formatter: (cell: CellComponent) =>
                formatDate(cell.getValue() as string | null, i18n.language, noDate),
        };

        const actionsColumn: ColumnDefinition = {
            title: t('common.actions'),
            field: 'actions',
            width: 104,
            hozAlign: 'right',
            headerHozAlign: 'right',
            headerSort: false,
            formatter: (cell: CellComponent) => {
                const row = cell.getRow().getData() as WorkOrderListItem;
                const parts: string[] = [];
                const name = subjectLabel(row, empty);

                if (canRef.current.update) {
                    parts.push(tabulatorEditLink(service.editPath(row.id), t('common.editItem', { name })));
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
                const row = cell.getRow().getData() as WorkOrderListItem;
                const confirmed = await confirmAction({
                    title: t('common.deleteTitle', { resource: t(`${resourceKey}.resource`) }),
                    message: t('common.deleteMessage', {
                        name: subjectLabel(row, empty),
                    }),
                    confirmLabel: t('common.delete'),
                    tone: 'danger',
                });

                if (!confirmed) {
                    return;
                }

                service.destroy(row.id, {
                    preserveScroll: true,
                    onSuccess: () => {
                        getTable()?.replaceData();
                    },
                });
            },
        };

        if (isEstimate) {
            return [
                subjectColumn,
                typeColumn,
                statusColumn,
                createdColumn,
                {
                    title: t('establishments.documentColumns.expectedCloseAt'),
                    field: 'expected_close_at',
                    minWidth: 150,
                    headerSort: false,
                    cssClass: 'cell-muted',
                    formatter: (cell: CellComponent) =>
                        formatDate(cell.getValue() as string | null, i18n.language, noDate),
                },
                {
                    title: t('establishments.documentColumns.closedAt'),
                    field: 'closed_at',
                    minWidth: 140,
                    headerSort: false,
                    cssClass: 'cell-muted',
                    formatter: (cell: CellComponent) =>
                        formatDate(cell.getValue() as string | null, i18n.language, empty),
                },
                {
                    title: t('establishments.documentColumns.totalCost'),
                    field: 'cost_amount',
                    minWidth: 110,
                    headerSort: false,
                    hozAlign: 'right',
                    cssClass: 'cell-muted',
                    formatter: (cell: CellComponent) =>
                        formatAmount(cell.getValue() as number | null, i18n.language, empty),
                },
                {
                    title: t('establishments.documentColumns.totalAmount'),
                    field: 'total_euros',
                    minWidth: 110,
                    headerSort: false,
                    hozAlign: 'right',
                    cssClass: 'cell-muted',
                    formatter: (cell: CellComponent) =>
                        formatAmount(cell.getValue() as number | null, i18n.language, empty),
                },
                {
                    title: t('establishments.documentColumns.margin'),
                    field: 'margin_percentage',
                    minWidth: 90,
                    headerSort: false,
                    hozAlign: 'right',
                    cssClass: 'cell-muted',
                    formatter: (cell: CellComponent) =>
                        formatPercent(cell.getValue() as number | null, i18n.language, empty),
                },
                actionsColumn,
            ];
        }

        return [
            subjectColumn,
            statusColumn,
            {
                title: t('establishments.documentColumns.priority'),
                field: 'priority_name',
                minWidth: 120,
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as WorkOrderListItem;
                    const name = row.priority_name;

                    if (!name) {
                        return `<span class="text-ink-muted">${empty}</span>`;
                    }

                    return tabulatorColorBadge(name, row.priority_color || '#94a3b8');
                },
            },
            typeColumn,
            createdColumn,
            {
                title: t('establishments.documentColumns.interventionAt'),
                field: 'intervention_at',
                minWidth: 150,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) =>
                    formatDate(cell.getValue() as string | null, i18n.language, noDate),
            },
            {
                title: t('establishments.documentColumns.technician'),
                field: 'technician_name',
                minWidth: 140,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) =>
                    (cell.getValue() as string | null) || t('establishments.documentColumns.noTechnician'),
            },
            {
                // Legacy establishment OT table header is "Coste Total" but the field is total_euros.
                title: t('establishments.documentColumns.totalCost'),
                field: 'total_euros',
                minWidth: 110,
                headerSort: false,
                hozAlign: 'right',
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) =>
                    formatAmount(cell.getValue() as number | null, i18n.language, empty),
            },
            actionsColumn,
        ];
    }

    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-ink">
                        {t(isEstimate ? 'establishments.estimatesTitle' : 'establishments.workOrdersTitle')}
                    </h2>
                    <p className="mt-1 text-sm text-ink-muted">
                        {t(
                            isEstimate
                                ? 'establishments.estimatesDescription'
                                : 'establishments.workOrdersDescription',
                        )}
                    </p>
                </div>

                {can.create ? (
                    <Link
                        href={`${service.createPath}?establishment_id=${establishmentId}`}
                        className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                        onClick={(event) => event.stopPropagation()}
                    >
                        <Plus className="size-3.5" aria-hidden />
                        {t(isEstimate ? 'estimates.new' : 'workOrders.new')}
                    </Link>
                ) : null}
            </div>

            <EstablishmentDocumentTotals totals={totals} />

            <RemoteDataTable<WorkOrderListItem>
                ref={tableRef}
                ajaxURL={service.dataPath}
                ajaxParams={() => ({
                    establishment_id: String(establishmentId),
                    pending: '',
                })}
                columns={buildColumns}
                initialSort={{ column: 'created_at', dir: 'desc' }}
                pageSize={25}
                emptyIcon={<ClipboardList className="size-5" aria-hidden />}
                emptyMessage={t('common.empty', { resource: t(`${resourceKey}.resourcePlural`) })}
                deps={[i18n.language, establishmentId, stage]}
            />
        </div>
    );
}
