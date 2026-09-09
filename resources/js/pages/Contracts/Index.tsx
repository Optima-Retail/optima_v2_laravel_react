import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { FileText, Plus, Settings2 } from 'lucide-react';
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
import { contractsService, numberingPatternsService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorColorBadge,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { ContractListItem } from '@/support/types/domain/contract';

type ContractsIndexProps = {
    filters: {
        search: string;
        sort: string;
        direction: string;
        per_page: string;
    };
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
        configure_pattern: boolean;
    };
};

export default function ContractsIndex({ filters, can }: ContractsIndexProps) {
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
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
            },
            {
                title: t('common.code'),
                field: 'code',
                minWidth: 110,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('contracts.description'),
                field: 'description',
                minWidth: 200,
                headerSort: true,
                cssClass: 'cell-strong',
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('contracts.status'),
                field: 'status_name',
                minWidth: 140,
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as ContractListItem;
                    const name = row.status_name;

                    if (!name) {
                        return `<span class="text-ink-muted">${t('common.emDash')}</span>`;
                    }

                    return tabulatorColorBadge(name, row.status_color || '#94a3b8');
                },
            },
            {
                title: t('contracts.brand'),
                field: 'brand_name',
                minWidth: 140,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('contracts.client'),
                field: 'company_name',
                minWidth: 160,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('contracts.totalAmount'),
                field: 'total_amount',
                minWidth: 120,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const value = cell.getValue();

                    if (value === null || value === undefined || value === '') {
                        return t('common.emDash');
                    }

                    return String(value);
                },
            },
            {
                title: t('contracts.signedAt'),
                field: 'signed_at',
                width: 120,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: 104,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const contract = cell.getRow().getData() as ContractListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                contractsService.editPath(contract.id),
                                t('common.editItem', { name: contract.description || contract.code || contract.id }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(
                            tabulatorDeleteButton(
                                t('common.deleteItem', {
                                    name: contract.description || contract.code || contract.id,
                                }),
                            ),
                        );
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const contract = cell.getRow().getData() as ContractListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('contracts.resource') }),
                        message: t('common.deleteMessage', {
                            name: contract.description || contract.code || contract.id,
                        }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    contractsService.destroy(contract.id, {
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
        <AppLayout title={t('contracts.title')}>
            <Head title={t('contracts.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('contracts.title')}
                    description={t('contracts.descriptionPage')}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            {can.configure_pattern ? (
                                <Link
                                    href={numberingPatternsService.configurePath('contracts', '/contracts')}
                                    className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-line bg-surface px-3 text-sm font-semibold text-ink transition-colors hover:bg-canvas"
                                >
                                    <Settings2 className="size-3.5" aria-hidden />
                                    {t('contracts.configurePattern')}
                                </Link>
                            ) : null}
                            {can.create ? (
                                <Link
                                    href={contractsService.createPath}
                                    className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                                >
                                    <Plus className="size-3.5" aria-hidden />
                                    {t('common.newItem', { resource: t('contracts.resource') })}
                                </Link>
                            ) : null}
                        </div>
                    }
                />

                <RemoteDataTable<ContractListItem>
                    ref={tableRef}
                    ajaxURL={contractsService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'id',
                        dir: filters.direction === 'asc' ? 'asc' : 'desc',
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
                            placeholder: t('contracts.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={contractsService.indexPath}
                    emptyIcon={<FileText className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('contracts.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
