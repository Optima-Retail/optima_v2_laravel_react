import { useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Landmark, Plus } from 'lucide-react';
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
import { banksService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { BankListItem } from '@/support/types/domain';

type BanksIndexProps = {
    filters: {
        search: string;
        status: string;
        sort: string;
        direction: string;
        per_page: string;
    };
    can: {
        create: boolean;
    };
};

export default function BanksIndex({ filters, can }: BanksIndexProps) {
    const { t, i18n } = useTranslation();
    const tableRef = useRef<RemoteDataTableHandle>(null);
    const canUpdate = useCan('banks.update');
    const canDelete = useCan('banks.delete');

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
                minWidth: 180,
                headerSort: true,
                cssClass: 'cell-strong',
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const bank = cell.getRow().getData() as BankListItem;

                    return bank.legal_name
                        ? `<div>${bank.name}</div><div class="text-xs text-ink-muted">${bank.legal_name}</div>`
                        : bank.name;
                },
            },
            {
                title: t('banks.country'),
                field: 'country_id',
                minWidth: 140,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const bank = cell.getRow().getData() as BankListItem;

                    return bank.country_name || t('common.emDash');
                },
            },
            {
                title: t('banks.swiftBic'),
                field: 'swift_bic',
                minWidth: 120,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('banks.nationalCode'),
                field: 'national_bank_code',
                minWidth: 120,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('common.status'),
                field: 'is_active',
                width: 110,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) =>
                    cell.getValue() ? t('common.active') : t('common.inactive'),
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: 104,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const bank = cell.getRow().getData() as BankListItem;
                    const parts: string[] = [];

                    if (canUpdate) {
                        parts.push(
                            tabulatorEditLink(
                                banksService.editPath(bank.id),
                                t('common.editItem', { name: bank.name }),
                            ),
                        );
                    }

                    if (canDelete) {
                        parts.push(tabulatorDeleteButton(t('common.deleteItem', { name: bank.name })));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const bank = cell.getRow().getData() as BankListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('banks.resource') }),
                        message: t('common.deleteMessage', { name: bank.name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    banksService.destroy(bank.id, {
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
        <AppLayout title={t('banks.title')}>
            <Head title={t('banks.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('banks.title')}
                    description={t('banks.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={banksService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.newItem', { resource: t('banks.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<BankListItem>
                    ref={tableRef}
                    ajaxURL={banksService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'name',
                        dir: filters.direction === 'desc' ? 'desc' : 'asc',
                    }}
                    pageSize={Number(filters.per_page) || 12}
                    initialFilters={{
                        search: filters.search,
                        status: filters.status,
                    }}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('banks.searchPlaceholder'),
                        },
                        {
                            type: 'select',
                            name: 'status',
                            label: t('common.status'),
                            emptyLabel: t('banks.allStatuses'),
                            options: [
                                { value: 'active', label: t('common.active') },
                                { value: 'inactive', label: t('common.inactive') },
                            ],
                        },
                    ]}
                    syncUrlBase={banksService.indexPath}
                    emptyIcon={<Landmark className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('banks.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
