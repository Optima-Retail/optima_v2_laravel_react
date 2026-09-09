import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Plus, Truck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import { badgeVariantForRelationshipStatus } from '@/components/ui/Badge';
import { PageHeader } from '@/components/page/PageHeader';
import {
    RemoteDataTable,
    type RemoteDataColumnHelpers,
    type RemoteDataTableHandle,
} from '@/components/table/RemoteDataTable';
import { confirmAction } from '@/helpers/confirm';
import { useCan } from '@/hooks/useAuth';
import { AppLayout } from '@/layouts/AppLayout';
import { suppliersService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorBadge,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { CompanyRelationshipListItem } from '@/support/types/domain/company-relationship';

type SuppliersIndexProps = {
    filters: {
        search: string;
        kind: string;
        sort: string;
        direction: string;
        per_page: string;
    };
    can: {
        create: boolean;
    };
};

const supplierKinds = ['supplier', 'technician'] as const;

export default function SuppliersIndex({ filters, can }: SuppliersIndexProps) {
    const { t, i18n } = useTranslation();
    const canUpdate = useCan('company_relationships.update');
    const canDelete = useCan('company_relationships.delete');
    const tableRef = useRef<RemoteDataTableHandle>(null);
    const canRef = useRef({ update: canUpdate, delete: canDelete });

    useEffect(() => {
        canRef.current = { update: canUpdate, delete: canDelete };
    }, [canUpdate, canDelete]);

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
                title: t('suppliers.relatedCompany'),
                field: 'related_company_name',
                minWidth: 200,
                headerSort: false,
                cssClass: 'cell-strong',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('suppliers.type'),
                field: 'kind',
                minWidth: 140,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) => t(`suppliers.kinds.${cell.getValue()}`),
            },
            {
                title: t('common.status'),
                field: 'status',
                minWidth: 140,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const status = String(cell.getValue() ?? '');

                    return tabulatorBadge(
                        t(`relationships.statuses.${status}`, { defaultValue: status }),
                        badgeVariantForRelationshipStatus(status),
                    );
                },
            },
            {
                title: t('suppliers.brand'),
                field: 'brand_name',
                minWidth: 160,
                headerSort: false,
                cssClass: 'cell-muted',
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
                    const supplier = cell.getRow().getData() as CompanyRelationshipListItem;
                    const parts: string[] = [];
                    const name = supplier.related_company_name ?? String(supplier.id);

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(suppliersService.editPath(supplier.id), t('common.editItem', { name })),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(tabulatorDeleteButton(t('common.deleteItem', { name })));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const supplier = cell.getRow().getData() as CompanyRelationshipListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('suppliers.resource') }),
                        message: t('common.deleteMessage', {
                            name: supplier.related_company_name ?? String(supplier.id),
                        }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    suppliersService.destroy(supplier.id, {
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
        <AppLayout title={t('suppliers.title')}>
            <Head title={t('suppliers.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('suppliers.title')}
                    description={t('suppliers.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={suppliersService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.newItem', { resource: t('suppliers.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<CompanyRelationshipListItem>
                    ref={tableRef}
                    ajaxURL={suppliersService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'id',
                        dir: filters.direction === 'asc' ? 'asc' : 'desc',
                    }}
                    pageSize={Number(filters.per_page) || 12}
                    initialFilters={{
                        search: filters.search,
                        kind: filters.kind,
                    }}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('suppliers.searchPlaceholder'),
                        },
                        {
                            type: 'select',
                            name: 'kind',
                            label: t('suppliers.type'),
                            emptyLabel: t('common.all'),
                            options: supplierKinds.map((kind) => ({
                                value: kind,
                                label: t(`suppliers.kinds.${kind}`),
                            })),
                        },
                    ]}
                    syncUrlBase={suppliersService.indexPath}
                    emptyIcon={<Truck className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('suppliers.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
