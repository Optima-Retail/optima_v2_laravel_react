import { useEffect, useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Layers, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import { IncidentTypeSubtypesModal } from '@/components/config/incident-types/IncidentTypeSubtypesModal';
import { TypesConfigTabs } from '@/components/config/TypesConfigTabs';
import { PageHeader } from '@/components/page/PageHeader';
import {
    RemoteDataTable,
    type RemoteDataColumnHelpers,
    type RemoteDataTableHandle,
} from '@/components/table/RemoteDataTable';
import { confirmAction } from '@/helpers/confirm';
import { useCan } from '@/hooks/useAuth';
import { AppLayout } from '@/layouts/AppLayout';
import { incidentTypesService } from '@/services';
import {
    isActionClick,
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorColorBadge,
    tabulatorDeleteButton,
    tabulatorEditLink,
    tabulatorSubtypesButton,
} from '@/support/tabulator';
import type { IncidentTypeListItem } from '@/support/types/domain/incident-type';

type IncidentTypesIndexProps = {
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

type SubtypesModalState = {
    incidentTypeId: number;
    incidentTypeName: string;
} | null;

export default function IncidentTypesIndex({ filters, can }: IncidentTypesIndexProps) {
    const { t, i18n } = useTranslation();
    const tableRef = useRef<RemoteDataTableHandle>(null);
    const canRef = useRef(can);
    const canViewSubtypes = useCan('incident_subtypes.view');
    const canEditSubtypes =
        useCan('incident_subtypes.create') ||
        useCan('incident_subtypes.update') ||
        useCan('incident_subtypes.delete');
    const [subtypesModal, setSubtypesModal] = useState<SubtypesModalState>(null);

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
                title: t('common.color'),
                field: 'color',
                minWidth: 140,
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const color = cell.getValue() as string | null;

                    if (!color) {
                        return `<span class="text-ink-muted">${t('common.emDash')}</span>`;
                    }

                    return `<span class="inline-flex items-center gap-2 text-ink-muted"><span class="inline-block size-3.5 rounded border border-line" style="background-color: ${color}"></span>${color}</span>`;
                },
            },
            {
                title: t('incidentTypes.defaultPriority'),
                field: 'default_priority_name',
                minWidth: 160,
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as IncidentTypeListItem;
                    const name = row.default_priority_name;
                    const color = row.default_priority_color;

                    if (!name) {
                        return `<span class="text-ink-muted">${t('common.emDash')}</span>`;
                    }

                    if (color) {
                        return tabulatorColorBadge(name, color);
                    }

                    return `<span class="text-ink">${name}</span>`;
                },
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: canViewSubtypes ? 140 : 104,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const type = cell.getRow().getData() as IncidentTypeListItem;
                    const parts: string[] = [];

                    if (canViewSubtypes) {
                        parts.push(
                            tabulatorSubtypesButton(
                                t('incidentSubtypes.manageForType', { name: type.name }),
                            ),
                        );
                    }

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                incidentTypesService.editPath(type.id),
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
                    const type = cell.getRow().getData() as IncidentTypeListItem;

                    if (isActionClick(event, 'subtypes')) {
                        event.preventDefault();
                        setSubtypesModal({
                            incidentTypeId: type.id,
                            incidentTypeName: type.name,
                        });

                        return;
                    }

                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('incidentTypes.resource') }),
                        message: t('common.deleteMessage', { name: type.name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    incidentTypesService.destroy(type.id, {
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
        <AppLayout title={t('nav.types')}>
            <Head title={t('nav.types')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('nav.types')}
                    description={t('incidentTypes.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={incidentTypesService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('incidentTypes.new')}
                            </Link>
                        ) : null
                    }
                />

                <TypesConfigTabs activeId="incident" />

                <RemoteDataTable<IncidentTypeListItem>
                    ref={tableRef}
                    ajaxURL={incidentTypesService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'id',
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
                            placeholder: t('incidentTypes.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={incidentTypesService.indexPath}
                    emptyIcon={<Layers className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('incidentTypes.resourcePlural') })}
                    deps={[i18n.language, canViewSubtypes]}
                />
            </div>

            {subtypesModal ? (
                <IncidentTypeSubtypesModal
                    open
                    incidentTypeId={subtypesModal.incidentTypeId}
                    incidentTypeName={subtypesModal.incidentTypeName}
                    canEdit={canEditSubtypes}
                    onClose={() => setSubtypesModal(null)}
                    onSaved={() => {
                        // list count is type-scoped; table itself unchanged
                    }}
                />
            ) : null}
        </AppLayout>
    );
}
