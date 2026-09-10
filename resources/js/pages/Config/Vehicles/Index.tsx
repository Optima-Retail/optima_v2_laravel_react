import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Car, Plus } from 'lucide-react';
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
import { vehiclesService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { VehicleListItem } from '@/support/types/domain/vehicle';

type VehiclesIndexProps = {
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

function vehicleDisplayName(vehicle: VehicleListItem, fallback: string): string {
    const parts = [vehicle.brand, vehicle.model, vehicle.license_plate].filter(Boolean);

    return parts.length > 0 ? parts.join(' ') : fallback;
}

export default function VehiclesIndex({ filters, can }: VehiclesIndexProps) {
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
                title: t('vehicles.brand'),
                field: 'brand',
                minWidth: 140,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('vehicles.model'),
                field: 'model',
                minWidth: 140,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('vehicles.licensePlate'),
                field: 'license_plate',
                minWidth: 120,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('vehicles.technician'),
                field: 'technician_label',
                minWidth: 180,
                headerSort: false,
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
                    const vehicle = cell.getRow().getData() as VehicleListItem;
                    const name = vehicleDisplayName(vehicle, String(vehicle.id));
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                vehiclesService.editPath(vehicle.id),
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
                    const vehicle = cell.getRow().getData() as VehicleListItem;
                    const name = vehicleDisplayName(vehicle, String(vehicle.id));
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('vehicles.resource') }),
                        message: t('common.deleteMessage', { name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    vehiclesService.destroy(vehicle.id, {
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
        <AppLayout title={t('vehicles.title')}>
            <Head title={t('vehicles.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('vehicles.title')}
                    description={t('vehicles.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={vehiclesService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.newItem', { resource: t('vehicles.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<VehicleListItem>
                    ref={tableRef}
                    ajaxURL={vehiclesService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'brand',
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
                            placeholder: t('vehicles.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={vehiclesService.indexPath}
                    emptyIcon={<Car className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('vehicles.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
