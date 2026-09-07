import { useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Plus, Tags } from 'lucide-react';
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
import { brandsService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { BrandListItem } from '@/support/types/domain';

type BrandsIndexProps = {
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

export default function BrandsIndex({ filters, can }: BrandsIndexProps) {
    const { t, i18n } = useTranslation();
    const tableRef = useRef<RemoteDataTableHandle>(null);
    const canUpdate = useCan('brands.update');
    const canDelete = useCan('brands.delete');

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
                title: t('common.name'),
                field: 'name',
                minWidth: 160,
                headerSort: true,
                cssClass: 'cell-strong',
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const brand = cell.getRow().getData() as BrandListItem;

                    if (!canUpdate) {
                        return brand.name;
                    }

                    return `<a href="${brandsService.editPath(brand.id)}" class="transition-colors hover:text-brand">${brand.name}</a>`;
                },
            },
            {
                title: t('brands.accountManager'),
                field: 'account_manager_name',
                minWidth: 160,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('brands.commercialManager'),
                field: 'commercial_manager_name',
                minWidth: 160,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('brands.meetingFrequency'),
                field: 'loyalty_meeting_frequency',
                minWidth: 140,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('brands.qcContact'),
                field: 'is_quality_control_contactable',
                minWidth: 120,
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const value = cell.getValue() as boolean;

                    if (!value) {
                        return `<span class="text-ink-muted">${t('common.no')}</span>`;
                    }

                    const badgeIcon =
                        '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0" aria-hidden="true"><path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z"/><path d="m9 12 2 2 4-4"/></svg>';

                    return `<span class="inline-flex items-center gap-1 text-success">${badgeIcon}${t('common.yes')}</span>`;
                },
            },
            {
                title: t('common.createdAt'),
                field: 'created_at',
                minWidth: 160,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const value = cell.getValue() as string | null;

                    return value ? new Date(value).toLocaleString() : t('common.emDash');
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
                    const brand = cell.getRow().getData() as BrandListItem;
                    const parts: string[] = [];

                    if (canUpdate) {
                        parts.push(
                            tabulatorEditLink(
                                brandsService.editPath(brand.id),
                                t('common.editItem', { name: brand.name }),
                            ),
                        );
                    }

                    if (canDelete) {
                        parts.push(tabulatorDeleteButton(t('common.deleteItem', { name: brand.name })));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const brand = cell.getRow().getData() as BrandListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('brands.resource') }),
                        message: t('common.deleteMessage', { name: brand.name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    brandsService.destroy(brand.id);
                    getTable()?.replaceData();
                },
            },
        ];
    }

    return (
        <AppLayout title={t('brands.title')}>
            <Head title={t('brands.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('brands.title')}
                    description={t('brands.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={brandsService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.newItem', { resource: t('brands.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<BrandListItem>
                    ref={tableRef}
                    ajaxURL={brandsService.dataPath}
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
                            placeholder: t('brands.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={brandsService.indexPath}
                    emptyIcon={<Tags className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('brands.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
