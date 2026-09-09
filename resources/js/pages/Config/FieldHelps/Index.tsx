import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { CircleHelp, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import { PageHeader } from '@/components/page/PageHeader';
import {
    RemoteDataTable,
    type RemoteDataColumnHelpers,
    type RemoteDataTableHandle,
} from '@/components/table/RemoteDataTable';
import { confirmAction } from '@/helpers/confirm';
import { useCan } from '@/hooks/useAuth';
import { AppLayout } from '@/layouts/AppLayout';
import { fieldHelpsService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
    tabulatorStatusBadge,
} from '@/support/tabulator';
import type { FieldHelpListItem } from '@/support/types/domain/field-help';

type FieldHelpsIndexProps = {
    filters: {
        search: string;
        sort: string;
        direction: string;
        per_page: string;
    };
    can: {
        create: boolean;
    };
};

export default function FieldHelpsIndex({ filters, can }: FieldHelpsIndexProps) {
    const { t, i18n } = useTranslation();
    const tableRef = useRef<RemoteDataTableHandle>(null);
    const canUpdate = useCan('field_helps.update');
    const canDelete = useCan('field_helps.delete');
    const canRef = useRef({ update: canUpdate, delete: canDelete });

    useEffect(() => {
        canRef.current = { update: canUpdate, delete: canDelete };
    }, [canUpdate, canDelete]);

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
                title: t('fieldHelps.key'),
                field: 'key',
                minWidth: 200,
                headerSort: true,
                titleFormatter,
            },
            {
                title: t('fieldHelps.helpTitle'),
                field: 'title',
                minWidth: 160,
                headerSort: false,
                titleFormatter,
            },
            {
                title: t('common.status'),
                field: 'is_active',
                minWidth: 100,
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
                    const item = cell.getRow().getData() as FieldHelpListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                fieldHelpsService.editPath(item.id),
                                t('common.editItem', { name: item.key }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(tabulatorDeleteButton(t('common.deleteItem', { name: item.key })));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const item = cell.getRow().getData() as FieldHelpListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('fieldHelps.resource') }),
                        message: t('common.deleteMessage', { name: item.key }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    fieldHelpsService.destroy(item.id, {
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
        <AppLayout title={t('fieldHelps.title')}>
            <Head title={t('fieldHelps.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('fieldHelps.title')}
                    description={t('fieldHelps.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={fieldHelpsService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.newItem', { resource: t('fieldHelps.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<FieldHelpListItem>
                    ref={tableRef}
                    ajaxURL={fieldHelpsService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'key',
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
                            placeholder: t('fieldHelps.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={fieldHelpsService.indexPath}
                    emptyIcon={<CircleHelp className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('fieldHelps.resourcePlural') })}
                    deps={[i18n.language, canUpdate, canDelete]}
                />
            </div>
        </AppLayout>
    );
}
