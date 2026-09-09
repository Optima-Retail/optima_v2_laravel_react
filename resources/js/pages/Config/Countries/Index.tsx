import { useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Globe2, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import { CountryProvincesModal } from '@/components/config/countries/CountryProvincesModal';
import { PageHeader } from '@/components/page/PageHeader';
import {
    RemoteDataTable,
    type RemoteDataColumnHelpers,
    type RemoteDataTableHandle,
} from '@/components/table/RemoteDataTable';
import { confirmAction } from '@/helpers/confirm';
import { useCan } from '@/hooks/useAuth';
import { AppLayout } from '@/layouts/AppLayout';
import { countriesService } from '@/services';
import {
    isActionClick,
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
    tabulatorProvincesButton,
} from '@/support/tabulator';
import type { CountryListItem } from '@/support/types/domain/country';

type CountriesIndexProps = {
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

type ProvincesModalState = {
    countryId: number;
    countryName: string;
} | null;

export default function CountriesIndex({ filters, can }: CountriesIndexProps) {
    const { t, i18n } = useTranslation();
    const tableRef = useRef<RemoteDataTableHandle>(null);
    const canUpdate = useCan('countries.update');
    const canDelete = useCan('countries.delete');
    const canViewProvinces = useCan('provinces.view');
    const canEditProvinces =
        useCan('provinces.create') || useCan('provinces.update') || useCan('provinces.delete');
    const [provincesModal, setProvincesModal] = useState<ProvincesModalState>(null);

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
                title: t('countries.iso'),
                field: 'iso_code',
                minWidth: 120,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('countries.timezone'),
                field: 'timezone_id',
                minWidth: 160,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const country = cell.getRow().getData() as CountryListItem;

                    return country.timezone_name ?? t('common.emDash');
                },
            },
            {
                title: t('countries.provincesCount'),
                field: 'provinces_count',
                minWidth: 120,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) => String(cell.getValue() ?? 0),
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: canViewProvinces ? 140 : 104,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const country = cell.getRow().getData() as CountryListItem;
                    const parts: string[] = [];

                    if (canViewProvinces) {
                        parts.push(
                            tabulatorProvincesButton(
                                t('provinces.manageForCountry', { name: country.name }),
                            ),
                        );
                    }

                    if (canUpdate) {
                        parts.push(
                            tabulatorEditLink(
                                countriesService.editPath(country.id),
                                t('common.editItem', { name: country.name }),
                            ),
                        );
                    }

                    if (canDelete) {
                        parts.push(
                            tabulatorDeleteButton(t('common.deleteItem', { name: country.name })),
                        );
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    const country = cell.getRow().getData() as CountryListItem;

                    if (isActionClick(event, 'provinces')) {
                        event.preventDefault();
                        setProvincesModal({
                            countryId: country.id,
                            countryName: country.name,
                        });

                        return;
                    }

                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('countries.resource') }),
                        message: t('common.deleteMessage', { name: country.name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    countriesService.destroy(country.id, {
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
        <AppLayout title={t('countries.title')}>
            <Head title={t('countries.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('countries.title')}
                    description={t('countries.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={countriesService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.newItem', { resource: t('countries.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<CountryListItem>
                    ref={tableRef}
                    ajaxURL={countriesService.dataPath}
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
                            placeholder: t('countries.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={countriesService.indexPath}
                    emptyIcon={<Globe2 className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('countries.resourcePlural') })}
                    deps={[i18n.language, canUpdate, canDelete, canViewProvinces]}
                />
            </div>

            {provincesModal ? (
                <CountryProvincesModal
                    open
                    countryId={provincesModal.countryId}
                    countryName={provincesModal.countryName}
                    canEdit={canEditProvinces}
                    onClose={() => setProvincesModal(null)}
                    onSaved={() => {
                        tableRef.current?.replaceData();
                    }}
                />
            ) : null}
        </AppLayout>
    );
}
