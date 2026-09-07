import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Plus, Star } from 'lucide-react';
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
import { ratingTypesService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { RatingTypeListItem } from '@/support/types/domain';

type RatingTypesIndexProps = {
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

export default function RatingTypesIndex({ filters, can }: RatingTypesIndexProps) {
    const { t, i18n } = useTranslation();
    const tableRef = useRef<RemoteDataTableHandle>(null);
    const canUpdate = useCan('rating_types.update');
    const canDelete = useCan('rating_types.delete');
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
            },
            {
                title: t('ratingTypes.maxScore'),
                field: 'max_score',
                minWidth: 120,
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
                    const ratingType = cell.getRow().getData() as RatingTypeListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                ratingTypesService.editPath(ratingType.id),
                                t('common.editItem', { name: ratingType.name }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(
                            tabulatorDeleteButton(t('common.deleteItem', { name: ratingType.name })),
                        );
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const ratingType = cell.getRow().getData() as RatingTypeListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('ratingTypes.resource') }),
                        message: t('common.deleteMessage', { name: ratingType.name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    ratingTypesService.destroy(ratingType.id, {
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
        <AppLayout title={t('ratingTypes.title')}>
            <Head title={t('ratingTypes.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('ratingTypes.title')}
                    description={t('ratingTypes.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={ratingTypesService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.newItem', { resource: t('ratingTypes.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<RatingTypeListItem>
                    ref={tableRef}
                    ajaxURL={ratingTypesService.dataPath}
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
                            placeholder: t('ratingTypes.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={ratingTypesService.indexPath}
                    emptyIcon={<Star className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('ratingTypes.resourcePlural') })}
                    deps={[i18n.language, canUpdate, canDelete]}
                />
            </div>
        </AppLayout>
    );
}
