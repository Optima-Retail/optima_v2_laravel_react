import { useMemo, useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Plus, Tags } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import { BrandClientsModal } from '@/components/config/brands/BrandClientsModal';
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
    isActionClick,
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorClientsButton,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { BrandListItem } from '@/support/types/domain/brand';

type BrandsIndexProps = {
    filters: {
        search: string;
        created_at: string;
        account_manager_ids: string;
        commercial_manager_ids: string;
        collaborator_ids: string;
        sort: string;
        direction: string;
        per_page: string;
    };
    userOptions: Array<{ id: number; label: string }>;
    can: {
        create: boolean;
    };
};

type ClientsModalState = {
    brandId: number;
    brandName: string;
} | null;

export default function BrandsIndex({ filters, userOptions, can }: BrandsIndexProps) {
    const { t, i18n } = useTranslation();
    const tableRef = useRef<RemoteDataTableHandle>(null);
    const canUpdate = useCan('brands.update');
    const canDelete = useCan('brands.delete');
    const canEditClients = useCan('company_relationships.update');
    const [clientsModal, setClientsModal] = useState<ClientsModalState>(null);

    const userFilterOptions = useMemo(
        () =>
            userOptions.map((user) => ({
                value: String(user.id),
                label: user.label,
            })),
        [userOptions],
    );

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
                minWidth: 180,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('brands.meetingFrequency'),
                field: 'loyalty_meeting_frequency',
                minWidth: 180,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('brands.clientsCount'),
                field: 'clients_count',
                width: 110,
                headerSort: true,
                hozAlign: 'right',
                headerHozAlign: 'right',
                titleFormatter,
                formatter: (cell: CellComponent) => String(cell.getValue() ?? 0),
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: 140,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const brand = cell.getRow().getData() as BrandListItem;
                    const parts: string[] = [
                        tabulatorClientsButton(t('brands.viewClients', { name: brand.name })),
                    ];

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
                    const brand = cell.getRow().getData() as BrandListItem;

                    if (isActionClick(event, 'clients')) {
                        event.preventDefault();
                        setClientsModal({
                            brandId: brand.id,
                            brandName: brand.name,
                        });

                        return;
                    }

                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
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
                    pageSize={Number(filters.per_page) || 25}
                    initialFilters={{
                        search: filters.search,
                        created_at: filters.created_at,
                        account_manager_ids: filters.account_manager_ids,
                        commercial_manager_ids: filters.commercial_manager_ids,
                        collaborator_ids: filters.collaborator_ids,
                    }}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('brands.searchPlaceholder'),
                        },
                        {
                            type: 'date',
                            name: 'created_at',
                            label: t('filters.createdAt'),
                        },
                        {
                            type: 'multiselect',
                            name: 'account_manager_ids',
                            label: t('brands.accountManager'),
                            options: userFilterOptions,
                            placeholder: t('brands.filterAccountManagers'),
                        },
                        {
                            type: 'multiselect',
                            name: 'commercial_manager_ids',
                            label: t('brands.commercialManager'),
                            options: userFilterOptions,
                            placeholder: t('brands.filterCommercialManagers'),
                        },
                        {
                            type: 'multiselect',
                            name: 'collaborator_ids',
                            label: t('brands.collaborators'),
                            options: userFilterOptions,
                            placeholder: t('brands.collaboratorsPlaceholder'),
                        },
                    ]}
                    savedFiltersPageKey="brands"
                    syncUrlBase={brandsService.indexPath}
                    emptyIcon={<Tags className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('brands.resourcePlural') })}
                    deps={[i18n.language, canUpdate, canDelete, userFilterOptions]}
                />
            </div>

            {clientsModal ? (
                <BrandClientsModal
                    open
                    brandId={clientsModal.brandId}
                    brandName={clientsModal.brandName}
                    canEdit={canEditClients}
                    onClose={() => setClientsModal(null)}
                />
            ) : null}
        </AppLayout>
    );
}
