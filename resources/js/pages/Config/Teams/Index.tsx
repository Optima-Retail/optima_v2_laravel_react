import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Plus, UsersRound } from 'lucide-react';
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
import { teamsService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { TeamListItem } from '@/support/types/domain';

type TeamsIndexProps = {
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

export default function TeamsIndex({ filters, can }: TeamsIndexProps) {
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
                cssClass: 'cell-muted',
                titleFormatter,
            },
            {
                title: t('common.code'),
                field: 'code',
                minWidth: 120,
                headerSort: true,
                cssClass: 'cell-strong',
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
                title: t('teams.manager'),
                field: 'manager_id',
                minWidth: 140,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: () => t('common.emDash'),
            },
            {
                title: t('teams.controller'),
                field: 'controller_id',
                minWidth: 140,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: () => t('common.emDash'),
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: 104,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const team = cell.getRow().getData() as TeamListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                teamsService.editPath(team.id),
                                t('common.editItem', { name: team.name }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(tabulatorDeleteButton(t('common.deleteItem', { name: team.name })));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const team = cell.getRow().getData() as TeamListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('teams.resource') }),
                        message: t('common.deleteMessage', { name: team.name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    teamsService.destroy(team.id, {
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
        <AppLayout title={t('teams.title')}>
            <Head title={t('teams.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('teams.title')}
                    description={t('teams.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={teamsService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.newItem', { resource: t('teams.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<TeamListItem>
                    ref={tableRef}
                    ajaxURL={teamsService.dataPath}
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
                            placeholder: t('teams.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={teamsService.indexPath}
                    emptyIcon={<UsersRound className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('teams.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
