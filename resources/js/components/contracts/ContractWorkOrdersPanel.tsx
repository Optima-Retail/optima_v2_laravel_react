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
import { workOrdersService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorColorBadge,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { WorkOrderListItem } from '@/support/types/domain/work-order';

type ContractWorkOrdersPanelProps = {
    contractId: number;
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

export function ContractWorkOrdersPanel({
    contractId,
    totals,
    can,
}: ContractWorkOrdersPanelProps) {
    const { t, i18n } = useTranslation();
    const tableRef = useRef<RemoteDataTableHandle>(null);
    const canRef = useRef(can);
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

                return tabulatorEditLink(workOrdersService.editPath(row.id), label);
            },
        };

        const statusColumn: ColumnDefinition = {
            title: t('establishments.documentColumns.status'),
            field: 'status_name',
            minWidth: 130,
            headerSort: false,
            formatter: (cell: CellComponent) => {
                const row = cell.getRow().getData() as WorkOrderListItem;

                return tabulatorColorBadge(row.status_name || empty, row.status_color);
            },
        };

        const typeColumn: ColumnDefinition = {
            title: t('establishments.documentColumns.type'),
            field: 'type_name',
            minWidth: 130,
            headerSort: false,
            cssClass: 'cell-muted',
            formatter: (cell: CellComponent) => (cell.getValue() as string | null) || empty,
        };

        const createdColumn: ColumnDefinition = {
            title: t('establishments.documentColumns.createdAt'),
            field: 'created_at',
            minWidth: 150,
            headerSort: true,
            cssClass: 'cell-muted',
            formatter: (cell: CellComponent) =>
                formatDate(cell.getValue() as string | null, i18n.language, noDate),
        };

        const actionsColumn: ColumnDefinition = {
            title: '',
            field: 'id',
            width: 88,
            headerSort: false,
            hozAlign: 'right',
            formatter: (cell: CellComponent) => {
                const row = cell.getRow().getData() as WorkOrderListItem;
                const buttons: string[] = [];

                if (canRef.current.update) {
                    buttons.push(tabulatorEditLink(workOrdersService.editPath(row.id), t('common.edit')));
                }

                if (canRef.current.delete) {
                    buttons.push(tabulatorDeleteButton(t('common.delete')));
                }

                return tabulatorActionsCell(buttons);
            },
            cellClick: async (event, cell) => {
                if (!isDeleteActionClick(event) || !canRef.current.delete) {
                    return;
                }

                const row = cell.getRow().getData() as WorkOrderListItem;
                const confirmed = await confirmAction({
                    title: t('common.deleteTitle', { resource: t('workOrders.resource') }),
                    message: t('common.deleteMessage', {
                        name: subjectLabel(row, String(row.id)),
                    }),
                    confirmLabel: t('common.delete'),
                    tone: 'danger',
                });

                if (!confirmed) {
                    return;
                }

                workOrdersService.destroy(row.id, {
                    onSuccess: () => getTable()?.replaceData(),
                });
            },
        };

        return [
            subjectColumn,
            {
                title: t('contracts.establishments'),
                field: 'establishment_name',
                minWidth: 160,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => (cell.getValue() as string | null) || empty,
            },
            statusColumn,
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
        <div className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-ink">{t('contracts.workOrdersTitle')}</h2>
                    <p className="mt-1 text-sm text-ink-muted">{t('contracts.workOrdersDescription')}</p>
                </div>

                {can.create ? (
                    <Link
                        href={`${workOrdersService.createPath}?contract_id=${contractId}`}
                        className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                        onClick={(event) => event.stopPropagation()}
                    >
                        <Plus className="size-3.5" aria-hidden />
                        {t('workOrders.new')}
                    </Link>
                ) : null}
            </div>

            <EstablishmentDocumentTotals totals={totals} />

            <RemoteDataTable<WorkOrderListItem>
                ref={tableRef}
                ajaxURL={workOrdersService.dataPath}
                ajaxParams={() => ({
                    contract_id: String(contractId),
                    pending: '',
                })}
                columns={buildColumns}
                initialSort={{ column: 'created_at', dir: 'desc' }}
                pageSize={25}
                emptyIcon={<ClipboardList className="size-5" aria-hidden />}
                emptyMessage={t('common.empty', { resource: t('workOrders.resourcePlural') })}
                deps={[i18n.language, contractId]}
            />
        </div>
    );
}
