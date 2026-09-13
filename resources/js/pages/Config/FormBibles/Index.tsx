import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { BookOpen, Plus } from 'lucide-react';
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
import { formBiblesService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';

type ListItem = { id: number; name: string; created_at: string | null };

type IndexProps = {
    filters: { search: string; sort: string; direction: string; per_page: string };
    can: { create: boolean; update: boolean; delete: boolean };
};

export default function FormBiblesIndex({ filters, can }: IndexProps) {
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
                minWidth: 220,
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
                    const row = cell.getRow().getData() as ListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                formBiblesService.editPath(row.id),
                                t('common.editItem', { name: row.name }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(tabulatorDeleteButton(t('common.delete')));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event) || !canRef.current.delete) {
                        return;
                    }

                    const row = cell.getRow().getData() as ListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('formBibles.resource') }),
                        message: t('common.deleteMessage', { name: row.name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    formBiblesService.destroy(row.id, {
                        preserveScroll: true,
                        onSuccess: () => getTable()?.replaceData(),
                    });
                },
            },
        ];
    }

    return (
        <AppLayout title={t('formBibles.title')}>
            <Head title={t('formBibles.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('formBibles.title')}
                    description={t('formBibles.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={formBiblesService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand/90"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.newItem', { resource: t('formBibles.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<ListItem>
                    ref={tableRef}
                    ajaxURL={formBiblesService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'name',
                        dir: filters.direction === 'desc' ? 'desc' : 'asc',
                    }}
                    pageSize={Number(filters.per_page) || 12}
                    initialFilters={{ search: filters.search }}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('formBibles.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={formBiblesService.indexPath}
                    emptyIcon={<BookOpen className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('formBibles.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
