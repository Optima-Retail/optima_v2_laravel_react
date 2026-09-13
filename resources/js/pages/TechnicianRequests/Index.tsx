import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ClipboardPen, Plus } from 'lucide-react';
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
import { technicianRequestsService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';

type TechnicianRequestListItem = {
    id: number;
    code: string | null;
    description: string | null;
    is_screening: boolean;
    status_name: string | null;
    status_color: string | null;
    priority_name: string | null;
    priority_color: string | null;
    responsible_name: string | null;
    city: string | null;
    due_at: string | null;
    resolved_at: string | null;
    created_at: string | null;
};

type Option = { id: number; label: string; kind?: string };

type TechnicianRequestsIndexProps = {
    filters: {
        search: string;
        technician_request_status_id: string;
        technician_request_priority_id: string;
        is_screening: string;
        responsible_user_id: string;
        created_from: string;
        created_to: string;
        due_from: string;
        due_to: string;
        sort: string;
        direction: string;
        per_page: string;
    };
    statusOptions: Option[];
    priorityOptions: Option[];
    userOptions: Option[];
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
};

export default function TechnicianRequestsIndex({
    filters,
    statusOptions,
    priorityOptions,
    userOptions,
    can,
}: TechnicianRequestsIndexProps) {
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
                title: t('technicianRequests.code'),
                field: 'code',
                minWidth: 120,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as TechnicianRequestListItem;
                    const code = row.code || t('common.emDash');
                    const kind = row.is_screening
                        ? t('technicianRequests.screening')
                        : t('technicianRequests.request');

                    return `<span>${code}</span><div class="text-xs text-ink-muted">${kind}</div>`;
                },
            },
            {
                title: t('technicianRequests.description'),
                field: 'description',
                minWidth: 180,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('technicianRequests.status'),
                field: 'status_name',
                minWidth: 120,
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as TechnicianRequestListItem;
                    const name = row.status_name || t('common.emDash');

                    if (!row.status_color) {
                        return name;
                    }

                    return `<span class="inline-flex items-center gap-2"><span class="inline-block size-2.5 rounded-full" style="background-color: ${row.status_color}"></span>${name}</span>`;
                },
            },
            {
                title: t('technicianRequests.priority'),
                field: 'priority_name',
                minWidth: 120,
                headerSort: false,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('technicianRequests.responsible'),
                field: 'responsible_name',
                minWidth: 140,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('technicianRequests.dueAt'),
                field: 'due_at',
                minWidth: 140,
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
                    const item = cell.getRow().getData() as TechnicianRequestListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                technicianRequestsService.editPath(item.id),
                                t('common.editItem', { name: item.code || item.id }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(
                            tabulatorDeleteButton(
                                t('common.deleteItem', { name: item.code || item.id }),
                            ),
                        );
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event) || !canRef.current.delete) {
                        return;
                    }

                    event.preventDefault();
                    const item = cell.getRow().getData() as TechnicianRequestListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('technicianRequests.resource') }),
                        message: t('common.deleteMessage', { name: item.code || item.id }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    technicianRequestsService.destroy(item.id, {
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
        <AppLayout title={t('technicianRequests.title')}>
            <Head title={t('technicianRequests.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('technicianRequests.title')}
                    description={t('technicianRequests.descriptionPage')}
                    actions={
                        can.create ? (
                            <Link
                                href={technicianRequestsService.createPath}
                                className="inline-flex h-9 items-center gap-2 rounded-lg bg-brand px-3 text-sm font-medium text-white"
                            >
                                <Plus className="size-4" aria-hidden />
                                {t('common.newItem', { resource: t('technicianRequests.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<TechnicianRequestListItem>
                    ref={tableRef}
                    ajaxURL={technicianRequestsService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'id',
                        dir: filters.direction === 'asc' ? 'asc' : 'desc',
                    }}
                    pageSize={Number(filters.per_page) || 12}
                    initialFilters={{
                        search: filters.search,
                        technician_request_status_id: filters.technician_request_status_id,
                        technician_request_priority_id: filters.technician_request_priority_id,
                        is_screening: filters.is_screening,
                        responsible_user_id: filters.responsible_user_id,
                        created_from: filters.created_from,
                        created_to: filters.created_to,
                        due_from: filters.due_from,
                        due_to: filters.due_to,
                    }}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('technicianRequests.searchPlaceholder'),
                        },
                        {
                            type: 'select',
                            name: 'is_screening',
                            label: t('technicianRequests.kind'),
                            emptyLabel: t('common.all'),
                            options: [
                                { value: '0', label: t('technicianRequests.request') },
                                { value: '1', label: t('technicianRequests.screening') },
                            ],
                        },
                        {
                            type: 'select',
                            name: 'technician_request_status_id',
                            label: t('technicianRequests.status'),
                            emptyLabel: t('common.all'),
                            options: statusOptions.map((option) => ({
                                value: String(option.id),
                                label: option.label,
                            })),
                        },
                        {
                            type: 'select',
                            name: 'technician_request_priority_id',
                            label: t('technicianRequests.priority'),
                            emptyLabel: t('common.all'),
                            options: priorityOptions.map((option) => ({
                                value: String(option.id),
                                label: option.label,
                            })),
                        },
                        {
                            type: 'select',
                            name: 'responsible_user_id',
                            label: t('technicianRequests.responsible'),
                            emptyLabel: t('common.all'),
                            options: userOptions.map((option) => ({
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
                        {
                            type: 'date',
                            name: 'due_from',
                            label: t('technicianRequests.dueFrom'),
                        },
                        {
                            type: 'date',
                            name: 'due_to',
                            label: t('technicianRequests.dueTo'),
                        },
                    ]}
                    savedFiltersPageKey="technician_requests"
                    syncUrlBase={technicianRequestsService.indexPath}
                    emptyIcon={<ClipboardPen className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('technicianRequests.resourcePlural') })}
                    deps={[i18n.language, can.create, can.update, can.delete]}
                />
            </div>
        </AppLayout>
    );
}
