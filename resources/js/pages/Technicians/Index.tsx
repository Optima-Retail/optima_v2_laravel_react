import { useEffect, useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Plus, TriangleAlert, Wrench } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import { TechnicianVehiclesModal } from '@/components/suppliers/TechnicianVehiclesModal';
import { badgeVariantForRelationshipStatus } from '@/components/ui/Badge';
import { PageHeader } from '@/components/page/PageHeader';
import {
    RemoteDataTable,
    type RemoteDataColumnHelpers,
    type RemoteDataTableHandle,
} from '@/components/table/RemoteDataTable';
import { confirmAction } from '@/helpers/confirm';
import { useCan } from '@/hooks/useAuth';
import { AppLayout } from '@/layouts/AppLayout';
import { techniciansService } from '@/services';
import {
    isActionClick,
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorBadge,
    tabulatorDeleteButton,
    tabulatorEditLink,
    tabulatorVehiclesButton,
} from '@/support/tabulator';
import type { CompanyRelationshipListItem } from '@/support/types/domain/company-relationship';

type TechniciansIndexProps = {
    filters: {
        search: string;
        kind: string;
        status: string;
        created_from: string;
        created_to: string;
        sort: string;
        direction: string;
        per_page: string;
    };
    can: {
        create: boolean;
    };
};

const relationshipStatuses = ['prospect', 'active', 'blocked', 'inactive', 'archived'] as const;

type VehiclesModalState = {
    relationshipId: number;
    technicianName: string;
} | null;

export default function TechniciansIndex({ filters, can }: TechniciansIndexProps) {
    const { t, i18n } = useTranslation();
    const canUpdate = useCan('company_relationships.update');
    const canDelete = useCan('company_relationships.delete');
    const canViewVehicles = useCan('vehicles.view');
    const canViewIncidents = useCan('technician_incidents.view');
    const canEditVehicles =
        canUpdate &&
        (useCan('vehicles.create') || useCan('vehicles.update') || useCan('vehicles.delete'));
    const tableRef = useRef<RemoteDataTableHandle>(null);
    const canRef = useRef({
        update: canUpdate,
        delete: canDelete,
        viewVehicles: canViewVehicles,
    });
    const [vehiclesModal, setVehiclesModal] = useState<VehiclesModalState>(null);

    useEffect(() => {
        canRef.current = {
            update: canUpdate,
            delete: canDelete,
            viewVehicles: canViewVehicles,
        };
    }, [canUpdate, canDelete, canViewVehicles]);

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
                title: t('technicians.relatedCompany'),
                field: 'related_company_name',
                minWidth: 200,
                headerSort: false,
                cssClass: 'cell-strong',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('common.status'),
                field: 'status',
                minWidth: 140,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const status = String(cell.getValue() ?? '');

                    return tabulatorBadge(
                        t(`relationships.statuses.${status}`, { defaultValue: status }),
                        badgeVariantForRelationshipStatus(status),
                    );
                },
            },
            {
                title: t('technicians.brand'),
                field: 'brand_name',
                minWidth: 160,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: canViewVehicles ? 140 : 104,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const technician = cell.getRow().getData() as CompanyRelationshipListItem;
                    const parts: string[] = [];
                    const name = technician.related_company_name ?? String(technician.id);

                    if (canRef.current.viewVehicles) {
                        parts.push(tabulatorVehiclesButton(t('technicians.viewVehicles', { name })));
                    }

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                techniciansService.editPath(technician.id),
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
                    const technician = cell.getRow().getData() as CompanyRelationshipListItem;
                    const name = technician.related_company_name ?? String(technician.id);

                    if (isActionClick(event, 'vehicles')) {
                        event.preventDefault();
                        setVehiclesModal({
                            relationshipId: technician.id,
                            technicianName: name,
                        });

                        return;
                    }

                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('technicians.resource') }),
                        message: t('common.deleteMessage', { name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    techniciansService.destroy(technician.id, {
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
        <AppLayout title={t('technicians.title')}>
            <Head title={t('technicians.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('technicians.title')}
                    description={t('technicians.description')}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            {canViewIncidents ? (
                                <Link
                                    href={techniciansService.incidentsPath}
                                    className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-line bg-surface px-3 text-sm font-semibold text-ink transition-colors hover:bg-canvas"
                                >
                                    <TriangleAlert className="size-3.5" aria-hidden />
                                    {t('technicianIncidents.resourcePlural')}
                                </Link>
                            ) : null}
                            {can.create ? (
                                <Link
                                    href={techniciansService.createPath}
                                    className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                                >
                                    <Plus className="size-3.5" aria-hidden />
                                    {t('common.newItem', { resource: t('technicians.resource') })}
                                </Link>
                            ) : null}
                        </div>
                    }
                />

                <RemoteDataTable<CompanyRelationshipListItem>
                    ref={tableRef}
                    ajaxURL={techniciansService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'id',
                        dir: filters.direction === 'asc' ? 'asc' : 'desc',
                    }}
                    pageSize={Number(filters.per_page) || 12}
                    initialFilters={{
                        search: filters.search,
                        status: filters.status,
                        created_from: filters.created_from,
                        created_to: filters.created_to,
                    }}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('technicians.searchPlaceholder'),
                        },
                        {
                            type: 'select',
                            name: 'status',
                            label: t('filters.status'),
                            emptyLabel: t('common.all'),
                            options: relationshipStatuses.map((status) => ({
                                value: status,
                                label: t(`relationships.statuses.${status}`),
                            })),
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
                    savedFiltersPageKey="technicians"
                    syncUrlBase={techniciansService.indexPath}
                    emptyIcon={<Wrench className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('technicians.resourcePlural') })}
                    deps={[i18n.language, canViewVehicles]}
                />
            </div>

            {vehiclesModal ? (
                <TechnicianVehiclesModal
                    open
                    relationshipId={vehiclesModal.relationshipId}
                    technicianName={vehiclesModal.technicianName}
                    canEdit={canEditVehicles}
                    onClose={() => setVehiclesModal(null)}
                />
            ) : null}
        </AppLayout>
    );
}
