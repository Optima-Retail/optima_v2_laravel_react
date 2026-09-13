import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Building2, Plus } from 'lucide-react';
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
import { companiesService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
    tabulatorStatusBadge,
} from '@/support/tabulator';
import type { CompanyListItem } from '@/support/types/domain/company';

type CompaniesIndexProps = {
    filters: {
        search: string;
        kind: string;
        is_active: string;
        created_from: string;
        created_to: string;
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

const kinds = ['operating_company', 'corporation', 'holding', 'ute', 'party'] as const;

export default function CompaniesIndex({ filters, can }: CompaniesIndexProps) {
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
                title: t('companies.taxId'),
                field: 'tax_id',
                minWidth: 120,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('companies.kind'),
                field: 'kind',
                minWidth: 140,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const kind = String(cell.getValue() ?? '');

                    return t(`companies.kinds.${kind}`, { defaultValue: kind });
                },
            },
            {
                title: t('common.status'),
                field: 'is_active',
                width: 110,
                headerSort: false,
                cssClass: 'cell-muted',
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
                    const company = cell.getRow().getData() as CompanyListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                companiesService.editPath(company.id),
                                t('common.editItem', { name: company.name }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(
                            tabulatorDeleteButton(t('common.deleteItem', { name: company.name })),
                        );
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const company = cell.getRow().getData() as CompanyListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('companies.resource') }),
                        message: t('common.deleteMessage', { name: company.name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    companiesService.destroy(company.id, {
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
        <AppLayout title={t('companies.title')}>
            <Head title={t('companies.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('companies.title')}
                    description={t('companies.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={companiesService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.newItem', { resource: t('companies.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<CompanyListItem>
                    ref={tableRef}
                    ajaxURL={companiesService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'name',
                        dir: filters.direction === 'desc' ? 'desc' : 'asc',
                    }}
                    pageSize={Number(filters.per_page) || 12}
                    initialFilters={{
                        search: filters.search,
                        kind: filters.kind,
                        is_active: filters.is_active,
                        created_from: filters.created_from,
                        created_to: filters.created_to,
                    }}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('companies.searchPlaceholder'),
                        },
                        {
                            type: 'select',
                            name: 'kind',
                            label: t('companies.kind'),
                            emptyLabel: t('common.all'),
                            options: kinds.map((kind) => ({
                                value: kind,
                                label: t(`companies.kinds.${kind}`),
                            })),
                        },
                        {
                            type: 'select',
                            name: 'is_active',
                            label: t('common.status'),
                            emptyLabel: t('common.all'),
                            options: [
                                { value: '1', label: t('filters.active') },
                                { value: '0', label: t('filters.inactive') },
                            ],
                        },
                        {
                            type: 'date',
                            name: 'created_from',
                            label: t('filters.createdFrom'),
                        },
                        {
                            type: 'date',
                            name: 'created_to',
                            label: t('filters.createdTo'),
                        },
                    ]}
                    savedFiltersPageKey="companies"
                    syncUrlBase={companiesService.indexPath}
                    emptyIcon={<Building2 className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('companies.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
