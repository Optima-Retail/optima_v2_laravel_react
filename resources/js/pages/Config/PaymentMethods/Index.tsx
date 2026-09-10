import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { CreditCard, Plus } from 'lucide-react';
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
import { paymentMethodsService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
    tabulatorStatusBadge,
} from '@/support/tabulator';
import type { PaymentMethodListItem } from '@/support/types/domain/payment-method';

type PaymentMethodsIndexProps = {
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

export default function PaymentMethodsIndex({ filters, can }: PaymentMethodsIndexProps) {
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
                minWidth: 180,
                headerSort: true,
                titleFormatter,
            },
            {
                title: t('paymentMethods.days'),
                field: 'days',
                width: 100,
                headerSort: true,
                titleFormatter,
            },
            {
                title: t('paymentMethods.code'),
                field: 'code',
                width: 120,
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
                    const method = cell.getRow().getData() as PaymentMethodListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                paymentMethodsService.editPath(method.id),
                                t('common.editItem', { name: method.name }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(tabulatorDeleteButton(t('common.deleteItem', { name: method.name })));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const method = cell.getRow().getData() as PaymentMethodListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('paymentMethods.resource') }),
                        message: t('common.deleteMessage', { name: method.name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    paymentMethodsService.destroy(method.id, {
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
        <AppLayout title={t('paymentMethods.title')}>
            <Head title={t('paymentMethods.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('paymentMethods.title')}
                    description={t('paymentMethods.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={paymentMethodsService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('paymentMethods.new')}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<PaymentMethodListItem>
                    ref={tableRef}
                    ajaxURL={paymentMethodsService.dataPath}
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
                            placeholder: t('paymentMethods.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={paymentMethodsService.indexPath}
                    emptyIcon={<CreditCard className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('paymentMethods.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
