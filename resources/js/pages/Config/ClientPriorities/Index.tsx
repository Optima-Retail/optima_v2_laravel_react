import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Flag, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import { PriorityConfigTabs } from '@/components/config/PriorityConfigTabs';
import { PageHeader } from '@/components/page/PageHeader';
import {
    RemoteDataTable,
    type RemoteDataColumnHelpers,
    type RemoteDataTableHandle,
} from '@/components/table/RemoteDataTable';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { clientPrioritiesService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { ClientPriorityListItem } from '@/support/types/domain/client-priority';

type ClientPrioritiesIndexProps = {
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

export default function ClientPrioritiesIndex({ filters, can }: ClientPrioritiesIndexProps) {
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
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('common.level'),
                field: 'level',
                width: 100,
                headerSort: true,
                titleFormatter,
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
                    const priority = cell.getRow().getData() as ClientPriorityListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                clientPrioritiesService.editPath(priority.id),
                                t('common.editItem', { name: priority.name }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(tabulatorDeleteButton(t('common.deleteItem', { name: priority.name })));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const priority = cell.getRow().getData() as ClientPriorityListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('clientPriorities.resource') }),
                        message: t('common.deleteMessage', { name: priority.name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    clientPrioritiesService.destroy(priority.id, {
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
        <AppLayout title={t('nav.priorities')}>
            <Head title={t('nav.priorities')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('nav.priorities')}
                    description={t('clientPriorities.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={clientPrioritiesService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('clientPriorities.new')}
                            </Link>
                        ) : null
                    }
                />

                <PriorityConfigTabs activeId="client" />

                <RemoteDataTable<ClientPriorityListItem>
                    ref={tableRef}
                    ajaxURL={clientPrioritiesService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'level',
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
                            placeholder: t('clientPriorities.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={clientPrioritiesService.indexPath}
                    emptyIcon={<Flag className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('clientPriorities.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
