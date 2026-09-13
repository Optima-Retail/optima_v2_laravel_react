import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Plus, TriangleAlert } from 'lucide-react';
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
import { incidentsService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorColorBadge,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { IncidentListItem } from '@/support/types/domain/incident';

type IncidentsIndexProps = {
    filters: {
        search: string;
        incident_status_id: string;
        created_from: string;
        created_to: string;
        sort: string;
        direction: string;
        per_page: string;
    };
    incidentStatusOptions: Array<{ id: number; label: string }>;
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
};

export default function IncidentsIndex({ filters, incidentStatusOptions, can }: IncidentsIndexProps) {
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
                title: t('incidents.subject'),
                field: 'subject',
                minWidth: 180,
                headerSort: true,
                cssClass: 'cell-strong',
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('incidents.establishment'),
                field: 'establishment_name',
                minWidth: 160,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('incidents.status'),
                field: 'status_name',
                minWidth: 140,
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as IncidentListItem;
                    const name = row.status_name;

                    if (!name) {
                        return `<span class="text-ink-muted">${t('common.emDash')}</span>`;
                    }

                    return tabulatorColorBadge(name, row.status_color || '#94a3b8');
                },
            },
            {
                title: t('incidents.priority'),
                field: 'priority_name',
                minWidth: 120,
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as IncidentListItem;
                    const name = row.priority_name;

                    if (!name) {
                        return `<span class="text-ink-muted">${t('common.emDash')}</span>`;
                    }

                    return tabulatorColorBadge(name, row.priority_color || '#94a3b8');
                },
            },
            {
                title: t('incidents.type'),
                field: 'type_name',
                minWidth: 120,
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as IncidentListItem;
                    const name = row.type_name;

                    if (!name) {
                        return `<span class="text-ink-muted">${t('common.emDash')}</span>`;
                    }

                    return tabulatorColorBadge(name, row.type_color || '#94a3b8');
                },
            },
            {
                title: t('incidents.responsibleUser'),
                field: 'responsible_user_name',
                minWidth: 140,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('incidents.controlAt'),
                field: 'control_at',
                minWidth: 160,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('common.createdAt'),
                field: 'created_at',
                minWidth: 140,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
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
                    const incident = cell.getRow().getData() as IncidentListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                incidentsService.editPath(incident.id),
                                t('common.editItem', {
                                    name: incident.subject || incident.id,
                                }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(
                            tabulatorDeleteButton(
                                t('common.deleteItem', {
                                    name: incident.subject || incident.id,
                                }),
                            ),
                        );
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const incident = cell.getRow().getData() as IncidentListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('incidents.resource') }),
                        message: t('common.deleteMessage', {
                            name: incident.subject || incident.id,
                        }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    incidentsService.destroy(incident.id, {
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
        <AppLayout title={t('incidents.title')}>
            <Head title={t('incidents.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('incidents.title')}
                    description={t('incidents.descriptionPage')}
                    actions={
                        can.create ? (
                            <Link
                                href={incidentsService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('incidents.new')}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<IncidentListItem>
                    ref={tableRef}
                    ajaxURL={incidentsService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'id',
                        dir: filters.direction === 'asc' ? 'asc' : 'desc',
                    }}
                    pageSize={Number(filters.per_page) || 12}
                    initialFilters={{
                        search: filters.search,
                        incident_status_id: filters.incident_status_id,
                        created_from: filters.created_from,
                        created_to: filters.created_to,
                    }}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('incidents.searchPlaceholder'),
                        },
                        {
                            type: 'select',
                            name: 'incident_status_id',
                            label: t('filters.status'),
                            emptyLabel: t('common.all'),
                            options: incidentStatusOptions.map((option) => ({
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
                    savedFiltersPageKey="incidents"
                    syncUrlBase={incidentsService.indexPath}
                    emptyIcon={<TriangleAlert className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('incidents.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
