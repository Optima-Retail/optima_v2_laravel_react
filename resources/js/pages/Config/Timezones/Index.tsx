import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Clock, Plus } from 'lucide-react';
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
import { timezonesService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { TimezoneListItem } from '@/support/types/domain';

type TimezonesIndexProps = {
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

export default function TimezonesIndex({ filters, can }: TimezonesIndexProps) {
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
                title: t('timezones.timezone'),
                field: 'timezone',
                minWidth: 180,
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
                    const timezone = cell.getRow().getData() as TimezoneListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                timezonesService.editPath(timezone.id),
                                t('common.editItem', { name: timezone.name }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(
                            tabulatorDeleteButton(t('common.deleteItem', { name: timezone.name })),
                        );
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const timezone = cell.getRow().getData() as TimezoneListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('timezones.resource') }),
                        message: t('common.deleteMessage', { name: timezone.name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    timezonesService.destroy(timezone.id, {
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
        <AppLayout title={t('timezones.title')}>
            <Head title={t('timezones.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('timezones.title')}
                    description={t('timezones.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={timezonesService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.newItem', { resource: t('timezones.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<TimezoneListItem>
                    ref={tableRef}
                    ajaxURL={timezonesService.dataPath}
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
                            placeholder: t('timezones.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={timezonesService.indexPath}
                    emptyIcon={<Clock className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('timezones.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
