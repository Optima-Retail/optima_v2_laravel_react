import { useEffect, useRef } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { CellComponent, ColumnDefinition, RowComponent } from 'tabulator-tables';
import { PageHeader } from '@/components/page/PageHeader';
import {
    RemoteDataTable,
    type RemoteDataColumnHelpers,
    type RemoteDataTableHandle,
} from '@/components/table/RemoteDataTable';
import { AppLayout } from '@/layouts/AppLayout';
import { technicianIncidentsService, techniciansService } from '@/services';
import type { TechnicianIncidentListItem } from '@/services/technicianIncidents';
import { tabulatorActionsCell, tabulatorColorBadge, tabulatorEditLink } from '@/support/tabulator';

type TechnicianIncidentsIndexProps = {
    filters: {
        search: string;
        status_id: string;
        created_from: string;
        created_to: string;
        sort: string;
        direction: string;
        per_page: string;
    };
    statusOptions: Array<{ id: number; label: string }>;
    can: {
        view: boolean;
        create: boolean;
    };
};

export default function TechnicianIncidentsIndex({ filters, statusOptions, can }: TechnicianIncidentsIndexProps) {
    const { t, i18n } = useTranslation();
    const tableRef = useRef<RemoteDataTableHandle>(null);
    const canRef = useRef(can);

    useEffect(() => {
        canRef.current = can;
    }, [can]);

    function openIncident(data: TechnicianIncidentListItem) {
        if (!data.technician_id) {
            return;
        }

        router.visit(
            techniciansService.editPath(data.technician_id, {
                tab: 'incidents',
                incident: data.id,
            }),
        );
    }

    function buildColumns({ titleFormatter }: RemoteDataColumnHelpers): ColumnDefinition[] {
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
                title: t('technicianIncidents.technician'),
                field: 'technician_name',
                minWidth: 160,
                headerSort: false,
                cssClass: 'cell-strong',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('technicianIncidents.type'),
                field: 'type_name',
                minWidth: 120,
                headerSort: false,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('technicianIncidents.requestedBy'),
                field: 'requested_by_name',
                minWidth: 140,
                headerSort: false,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('technicianIncidents.assignedTo'),
                field: 'responded_by_name',
                minWidth: 140,
                headerSort: false,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('common.status'),
                field: 'status_name',
                minWidth: 120,
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as TechnicianIncidentListItem;

                    return tabulatorColorBadge(row.status_name || t('common.emDash'), row.status_color || '#94a3b8');
                },
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: 72,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const incident = cell.getRow().getData() as TechnicianIncidentListItem;
                    const parts: string[] = [];

                    if (canRef.current.view && incident.technician_id) {
                        parts.push(
                            tabulatorEditLink(
                                techniciansService.editPath(incident.technician_id, {
                                    tab: 'incidents',
                                    incident: incident.id,
                                }),
                                t('common.editItem', {
                                    name: `#${incident.id}`,
                                }),
                            ),
                        );
                    }

                    return tabulatorActionsCell(parts);
                },
            },
        ];
    }

    return (
        <AppLayout title={t('technicianIncidents.title')}>
            <Head title={t('technicianIncidents.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('technicianIncidents.title')}
                    description={t('technicianIncidents.description')}
                    backHref={techniciansService.indexPath}
                    backLabel={t('common.backTo', { resource: t('technicians.resourcePlural') })}
                    actions={
                        can.create ? (
                            <Link
                                href={technicianIncidentsService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.create')}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<TechnicianIncidentListItem>
                    ref={tableRef}
                    ajaxURL={technicianIncidentsService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'id',
                        dir: filters.direction === 'asc' ? 'asc' : 'desc',
                    }}
                    pageSize={Number(filters.per_page) || 12}
                    initialFilters={{
                        search: filters.search,
                        status_id: filters.status_id,
                        created_from: filters.created_from,
                        created_to: filters.created_to,
                    }}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('technicianIncidents.searchPlaceholder'),
                        },
                        {
                            type: 'select',
                            name: 'status_id',
                            label: t('filters.status'),
                            emptyLabel: t('common.all'),
                            options: statusOptions.map((option) => ({
                                value: String(option.id),
                                label: option.label,
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
                    savedFiltersPageKey="technician_incidents"
                    syncUrlBase={technicianIncidentsService.indexPath}
                    emptyIcon={<TriangleAlert className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('technicianIncidents.resourcePlural') })}
                    deps={[i18n.language]}
                    options={{
                        rowClick: (event: UIEvent, row: RowComponent) => {
                            const target = event.target as HTMLElement | null;

                            if (target?.closest('[data-action], a, button')) {
                                return;
                            }

                            openIncident(row.getData() as TechnicianIncidentListItem);
                        },
                    }}
                />
            </div>
        </AppLayout>
    );
}
