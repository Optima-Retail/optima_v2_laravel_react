import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { CircleDot, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import { StatusConfigTabs } from '@/components/config/StatusConfigTabs';
import { PageHeader } from '@/components/page/PageHeader';
import {
    RemoteDataTable,
    type RemoteDataColumnHelpers,
    type RemoteDataTableHandle,
} from '@/components/table/RemoteDataTable';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { technicianRequestStatusesService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
    tabulatorStatusBadge,
} from '@/support/tabulator';

type TechnicianRequestStatusListItem = {
    id: number;
    kind: string;
    name: string;
    color: string | null;
    lifecycle: number | null;
    is_open: boolean;
    created_at: string | null;
};

type TechnicianRequestStatusesIndexProps = {
    filters: {
        search: string;
        kind: string;
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

export default function TechnicianRequestStatusesIndex({
    filters,
    can,
}: TechnicianRequestStatusesIndexProps) {
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
                title: t('technicianRequestStatuses.kind'),
                field: 'kind',
                width: 120,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const kind = String(cell.getValue() || '');

                    return t(`technicianRequests.${kind === 'screening' ? 'screening' : 'request'}`);
                },
            },
            {
                title: t('common.name'),
                field: 'name',
                minWidth: 200,
                headerSort: true,
                titleFormatter,
            },
            {
                title: t('technicianRequestStatuses.lifecycle'),
                field: 'lifecycle',
                width: 120,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const value = cell.getValue() as number | null;

                    return value === null || value === undefined
                        ? `<span class="text-ink-muted">${t('common.emDash')}</span>`
                        : String(value);
                },
            },
            {
                title: t('technicianRequestStatuses.isOpen'),
                field: 'is_open',
                width: 120,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) =>
                    tabulatorStatusBadge(Boolean(cell.getValue()), t('common.active'), t('common.inactive')),
            },
            {
                title: t('common.color'),
                field: 'color',
                minWidth: 140,
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const color = cell.getValue() as string | null;

                    if (!color) {
                        return `<span class="text-ink-muted">${t('common.emDash')}</span>`;
                    }

                    return `<span class="inline-flex items-center gap-2 text-ink-muted"><span class="inline-block size-3.5 rounded border border-line" style="background-color: ${color}"></span>${color}</span>`;
                },
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: 104,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const status = cell.getRow().getData() as TechnicianRequestStatusListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                technicianRequestStatusesService.editPath(status.id),
                                t('common.editItem', { name: status.name }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(tabulatorDeleteButton(t('common.deleteItem', { name: status.name })));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const status = cell.getRow().getData() as TechnicianRequestStatusListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', {
                            resource: t('technicianRequestStatuses.resource'),
                        }),
                        message: t('common.deleteMessage', { name: status.name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    technicianRequestStatusesService.destroy(status.id, {
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
        <AppLayout title={t('nav.statuses')}>
            <Head title={t('nav.statuses')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('nav.statuses')}
                    description={t('technicianRequestStatuses.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={technicianRequestStatusesService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('technicianRequestStatuses.new')}
                            </Link>
                        ) : null
                    }
                />

                <StatusConfigTabs activeId="technician-request" />

                <RemoteDataTable<TechnicianRequestStatusListItem>
                    ref={tableRef}
                    ajaxURL={technicianRequestStatusesService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'lifecycle',
                        dir: filters.direction === 'desc' ? 'desc' : 'asc',
                    }}
                    pageSize={Number(filters.per_page) || 25}
                    initialFilters={{
                        search: filters.search,
                        kind: filters.kind,
                    }}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('technicianRequestStatuses.searchPlaceholder'),
                        },
                        {
                            type: 'select',
                            name: 'kind',
                            label: t('technicianRequestStatuses.kind'),
                            emptyLabel: t('common.all'),
                            options: [
                                { value: 'request', label: t('technicianRequests.request') },
                                { value: 'screening', label: t('technicianRequests.screening') },
                            ],
                        },
                    ]}
                    syncUrlBase={technicianRequestStatusesService.indexPath}
                    emptyIcon={<CircleDot className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', {
                        resource: t('technicianRequestStatuses.resourcePlural'),
                    })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
