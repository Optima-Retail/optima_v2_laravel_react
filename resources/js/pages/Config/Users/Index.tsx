import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Plus, Users } from 'lucide-react';
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
import { usersService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
    tabulatorStatusBadge,
} from '@/support/tabulator';
import type { UserListItem } from '@/support/types/domain/user';

type UsersIndexProps = {
    filters: {
        search: string;
        role: string;
        sort: string;
        direction: string;
        per_page: string;
    };
    roleOptions: string[];
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
};

export default function UsersIndex({ filters, roleOptions, can }: UsersIndexProps) {
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
                title: t('common.name'),
                field: 'name',
                minWidth: 160,
                headerSort: true,
                cssClass: 'cell-strong',
                titleFormatter,
            },
            {
                title: t('common.email'),
                field: 'email',
                minWidth: 200,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
            },
            {
                title: t('common.roles'),
                field: 'roles',
                minWidth: 180,
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const roles = (cell.getValue() as string[] | undefined) ?? [];

                    return `<div class="flex flex-wrap gap-1">${roles
                        .map(
                            (role) =>
                                `<span class="rounded-md bg-brand-soft px-1.5 py-0.5 text-xs font-semibold text-brand">${role}</span>`,
                        )
                        .join('')}</div>`;
                },
            },
            {
                title: t('common.status'),
                field: 'is_active',
                width: 110,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) =>
                    tabulatorStatusBadge(Boolean(cell.getValue()), t('common.active'), t('common.inactive')),
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: 104,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const user = cell.getRow().getData() as UserListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                usersService.editPath(user.id),
                                t('common.editItem', { name: user.name }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(tabulatorDeleteButton(t('common.deleteItem', { name: user.name })));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const user = cell.getRow().getData() as UserListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('users.resource') }),
                        message: t('common.deleteMessage', { name: user.name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    usersService.destroy(user.id, {
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
        <AppLayout title={t('users.title')}>
            <Head title={t('users.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('users.title')}
                    description={t('users.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={usersService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.newItem', { resource: t('users.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<UserListItem>
                    ref={tableRef}
                    ajaxURL={usersService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'name',
                        dir: filters.direction === 'desc' ? 'desc' : 'asc',
                    }}
                    pageSize={Number(filters.per_page) || 12}
                    initialFilters={{
                        search: filters.search,
                        role: filters.role,
                    }}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('users.searchPlaceholder'),
                        },
                        {
                            type: 'select',
                            name: 'role',
                            label: t('roles.role'),
                            emptyLabel: t('users.allRoles'),
                            options: roleOptions.map((role) => ({ value: role, label: role })),
                        },
                    ]}
                    syncUrlBase={usersService.indexPath}
                    emptyIcon={<Users className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('users.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
