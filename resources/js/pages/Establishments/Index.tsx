import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Plus, Warehouse } from 'lucide-react';
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
import { establishmentsService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { EstablishmentListItem } from '@/support/types/domain';

type EstablishmentsIndexProps = {
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

export default function EstablishmentsIndex({ filters, can }: EstablishmentsIndexProps) {
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
                hozAlign: 'left',
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
            },
            {
                title: t('common.name'),
                field: 'name',
                minWidth: 160,
                headerSort: true,
                cssClass: 'cell-strong',
                titleFormatter,
            },
            {
                title: t('common.code'),
                field: 'code',
                minWidth: 120,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('establishments.client'),
                field: 'company_name',
                minWidth: 160,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('companies.city'),
                field: 'city',
                minWidth: 140,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('common.status'),
                field: 'is_active',
                width: 110,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) =>
                    cell.getValue() ? t('common.active') : t('common.inactive'),
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: 104,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const establishment = cell.getRow().getData() as EstablishmentListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                establishmentsService.editPath(establishment.id),
                                t('common.editItem', { name: establishment.name }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(
                            tabulatorDeleteButton(t('common.deleteItem', { name: establishment.name })),
                        );
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const establishment = cell.getRow().getData() as EstablishmentListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('establishments.resource') }),
                        message: t('common.deleteMessage', { name: establishment.name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    establishmentsService.destroy(establishment.id, {
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
        <AppLayout title={t('establishments.title')}>
            <Head title={t('establishments.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('establishments.title')}
                    description={t('establishments.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={establishmentsService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.newItem', { resource: t('establishments.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<EstablishmentListItem>
                    ref={tableRef}
                    ajaxURL={establishmentsService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'name',
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
                            placeholder: t('establishments.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={establishmentsService.indexPath}
                    emptyIcon={<Warehouse className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('establishments.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
