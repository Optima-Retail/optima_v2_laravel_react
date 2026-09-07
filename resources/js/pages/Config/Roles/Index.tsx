import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Plus, Shield } from 'lucide-react';
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
import { rolesService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { RoleListItem } from '@/support/types/domain';

type RolesIndexProps = {
    filters: {
        search: string;
        type: string;
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

export default function RolesIndex({ filters, can }: RolesIndexProps) {
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
                hozAlign: 'left',
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
            },
            {
                title: t('roles.role'),
                field: 'name',
                minWidth: 200,
                headerSort: true,
                cssClass: 'cell-strong',
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const role = cell.getRow().getData() as RoleListItem;
                    const badge = role.is_system
                        ? `<span class="rounded-md bg-brand-soft px-1.5 py-0.5 text-[11px] font-semibold text-brand">${t('common.system')}</span>`
                        : '';

                    return `<div class="flex items-center gap-2"><span class="font-medium text-ink">${role.name}</span>${badge}</div>`;
                },
            },
            {
                title: t('common.users'),
                field: 'users_count',
                width: 120,
                headerSort: false,
                cssClass: 'cell-muted',
            },
            {
                title: t('common.permissions'),
                field: 'permissions_count',
                width: 140,
                headerSort: false,
                cssClass: 'cell-muted',
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: 104,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const role = cell.getRow().getData() as RoleListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                rolesService.editPath(role.id),
                                t('common.editItem', { name: role.name }),
                            ),
                        );
                    }

                    if (canRef.current.delete && !role.is_system) {
                        parts.push(tabulatorDeleteButton(t('common.deleteItem', { name: role.name })));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const role = cell.getRow().getData() as RoleListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('roles.resource') }),
                        message: t('common.deleteMessage', { name: role.name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    rolesService.destroy(role.id, {
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
        <AppLayout title={t('roles.title')}>
            <Head title={t('roles.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('roles.title')}
                    description={t('roles.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={rolesService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.newItem', { resource: t('roles.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<RoleListItem>
                    ref={tableRef}
                    ajaxURL={rolesService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'name',
                        dir: filters.direction === 'desc' ? 'desc' : 'asc',
                    }}
                    pageSize={Number(filters.per_page) || 12}
                    initialFilters={{
                        search: filters.search,
                        type: filters.type,
                    }}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('roles.searchPlaceholder'),
                        },
                        {
                            type: 'select',
                            name: 'type',
                            label: t('common.type'),
                            emptyLabel: t('roles.allTypes'),
                            options: [
                                { value: 'system', label: t('roles.system') },
                                { value: 'custom', label: t('roles.custom') },
                            ],
                        },
                    ]}
                    syncUrlBase={rolesService.indexPath}
                    emptyIcon={<Shield className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('roles.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
