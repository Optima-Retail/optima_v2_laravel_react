import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Plus, Store } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import { PageHeader } from '@/components/page/PageHeader';
import {
    RemoteDataTable,
    type RemoteDataColumnHelpers,
    type RemoteDataTableHandle,
} from '@/components/table/RemoteDataTable';
import { confirmAction } from '@/helpers/confirm';
import { useCan } from '@/hooks/useAuth';
import { AppLayout } from '@/layouts/AppLayout';
import { establishmentTypesService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { EstablishmentTypeListItem } from '@/support/types/domain';

type EstablishmentTypesIndexProps = {
    filters: {
        search: string;
        sort: string;
        direction: string;
        per_page: string;
    };
    can: {
        create: boolean;
    };
};

export default function EstablishmentTypesIndex({ filters, can }: EstablishmentTypesIndexProps) {
    const { t, i18n } = useTranslation();
    const tableRef = useRef<RemoteDataTableHandle>(null);
    const canUpdate = useCan('establishment_types.update');
    const canDelete = useCan('establishment_types.delete');
    const canRef = useRef({ update: canUpdate, delete: canDelete });

    useEffect(() => {
        canRef.current = { update: canUpdate, delete: canDelete };
    }, [canUpdate, canDelete]);

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
                title: t('common.name'),
                field: 'name',
                minWidth: 180,
                headerSort: true,
                titleFormatter,
            },
            {
                title: t('common.code'),
                field: 'code',
                minWidth: 120,
                headerSort: true,
                titleFormatter,
            },
            {
                title: t('establishmentTypes.healthAndSafetyDelayDays'),
                field: 'health_and_safety_delay_days',
                minWidth: 160,
                headerSort: true,
                titleFormatter,
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: 104,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const establishmentType = cell.getRow().getData() as EstablishmentTypeListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                establishmentTypesService.editPath(establishmentType.id),
                                t('common.editItem', { name: establishmentType.name }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(
                            tabulatorDeleteButton(t('common.deleteItem', { name: establishmentType.name })),
                        );
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const establishmentType = cell.getRow().getData() as EstablishmentTypeListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('establishmentTypes.resource') }),
                        message: t('common.deleteMessage', { name: establishmentType.name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    establishmentTypesService.destroy(establishmentType.id, {
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
        <AppLayout title={t('establishmentTypes.title')}>
            <Head title={t('establishmentTypes.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('establishmentTypes.title')}
                    description={t('establishmentTypes.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={establishmentTypesService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.newItem', { resource: t('establishmentTypes.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<EstablishmentTypeListItem>
                    ref={tableRef}
                    ajaxURL={establishmentTypesService.dataPath}
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
                            placeholder: t('establishmentTypes.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={establishmentTypesService.indexPath}
                    emptyIcon={<Store className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('establishmentTypes.resourcePlural') })}
                    deps={[i18n.language, canUpdate, canDelete]}
                />
            </div>
        </AppLayout>
    );
}
