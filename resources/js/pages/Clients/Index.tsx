import { useEffect, useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Handshake, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import { ClientEstablishmentsModal } from '@/components/clients/ClientEstablishmentsModal';
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
import { clientsService } from '@/services';
import {
    isActionClick,
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorBadge,
    tabulatorDeleteButton,
    tabulatorEditLink,
    tabulatorEstablishmentsButton,
} from '@/support/tabulator';
import type { CompanyRelationshipListItem } from '@/support/types/domain/company-relationship';

type ClientsIndexProps = {
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

type EstablishmentsModalState = {
    relationshipId: number;
    clientName: string;
} | null;

export default function ClientsIndex({ filters, can }: ClientsIndexProps) {
    const { t, i18n } = useTranslation();
    const canUpdate = useCan('company_relationships.update');
    const canDelete = useCan('company_relationships.delete');
    const canEditEstablishments = useCan('establishments.update');
    const tableRef = useRef<RemoteDataTableHandle>(null);
    const canRef = useRef({ update: canUpdate, delete: canDelete });
    const [establishmentsModal, setEstablishmentsModal] = useState<EstablishmentsModalState>(null);

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
                title: t('clients.relatedCompany'),
                field: 'related_company_name',
                minWidth: 200,
                headerSort: false,
                cssClass: 'cell-strong',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
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
                title: t('clients.brand'),
                field: 'brand_name',
                minWidth: 160,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: 140,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const client = cell.getRow().getData() as CompanyRelationshipListItem;
                    const name = client.related_company_name ?? String(client.id);
                    const parts: string[] = [
                        tabulatorEstablishmentsButton(t('clients.viewEstablishments', { name })),
                    ];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(clientsService.editPath(client.id), t('common.editItem', { name })),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(tabulatorDeleteButton(t('common.deleteItem', { name })));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    const client = cell.getRow().getData() as CompanyRelationshipListItem;
                    const name = client.related_company_name ?? String(client.id);

                    if (isActionClick(event, 'establishments')) {
                        event.preventDefault();
                        setEstablishmentsModal({
                            relationshipId: client.id,
                            clientName: name,
                        });

                        return;
                    }

                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('clients.resource') }),
                        message: t('common.deleteMessage', { name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    clientsService.destroy(client.id, {
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
        <AppLayout title={t('clients.title')}>
            <Head title={t('clients.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('clients.title')}
                    description={t('clients.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={clientsService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.newItem', { resource: t('clients.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<CompanyRelationshipListItem>
                    ref={tableRef}
                    ajaxURL={clientsService.dataPath}
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
                            placeholder: t('clients.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={clientsService.indexPath}
                    emptyIcon={<Handshake className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('clients.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>

            {establishmentsModal ? (
                <ClientEstablishmentsModal
                    open
                    relationshipId={establishmentsModal.relationshipId}
                    clientName={establishmentsModal.clientName}
                    canEdit={canEditEstablishments}
                    onClose={() => setEstablishmentsModal(null)}
                />
            ) : null}
        </AppLayout>
    );
}
