import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Wallet, Plus } from 'lucide-react';
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
import { paymentDocumentsService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
    tabulatorStatusBadge,
} from '@/support/tabulator';
import type { PaymentDocumentListItem } from '@/support/types/domain/payment-document';

type PaymentDocumentsIndexProps = {
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
    };
};

export default function PaymentDocumentsIndex({ filters, can }: PaymentDocumentsIndexProps) {
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
                    const type = cell.getRow().getData() as PaymentDocumentListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                paymentDocumentsService.editPath(type.id),
                                t('common.editItem', { name: type.name }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(tabulatorDeleteButton(t('common.deleteItem', { name: type.name })));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const type = cell.getRow().getData() as PaymentDocumentListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('paymentDocuments.resource') }),
                        message: t('common.deleteMessage', { name: type.name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    paymentDocumentsService.destroy(type.id, {
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
        <AppLayout title={t('paymentDocuments.title')}>
            <Head title={t('paymentDocuments.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('paymentDocuments.title')}
                    description={t('paymentDocuments.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={paymentDocumentsService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('paymentDocuments.new')}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<PaymentDocumentListItem>
                    ref={tableRef}
                    ajaxURL={paymentDocumentsService.dataPath}
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
                            placeholder: t('paymentDocuments.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={paymentDocumentsService.indexPath}
                    emptyIcon={<Wallet className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('paymentDocuments.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
