import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { FileStack, Plus } from 'lucide-react';
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
import { formTemplatesService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';

type ListItem = {
    id: number;
    name: string;
    type_name: string | null;
    owner_type: string;
    owner_label: string;
    is_default: boolean;
    created_at: string | null;
};

type IndexProps = {
    filters: {
        search: string;
        form_type_id: string;
        owner_type: string;
        sort: string;
        direction: string;
        per_page: string;
    };
    typeOptions: Array<{ id: number; label: string }>;
    can: { create: boolean; update: boolean; delete: boolean };
};

const ownerTypes = ['global', 'brand', 'customer', 'establishment', 'bible'] as const;

export default function FormTemplatesIndex({ filters, typeOptions, can }: IndexProps) {
    const { t } = useTranslation();
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
                titleFormatter,
            },
            {
                title: t('common.name'),
                field: 'name',
                minWidth: 200,
                headerSort: true,
                cssClass: 'cell-strong',
                titleFormatter,
            },
            {
                title: t('formTemplates.type'),
                field: 'type_name',
                minWidth: 120,
                headerSort: false,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('formTemplates.owner'),
                field: 'owner_label',
                minWidth: 160,
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as ListItem;
                    const typeLabel = t(`formTemplates.ownerTypes.${row.owner_type}`, {
                        defaultValue: row.owner_type,
                    });
                    const label = row.owner_label || typeLabel;

                    return `<span>${label}</span><div class="text-xs text-ink-muted">${typeLabel}</div>`;
                },
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: 104,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as ListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                formTemplatesService.editPath(row.id),
                                t('common.editItem', { name: row.name }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(tabulatorDeleteButton(t('common.delete')));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (_event, cell) => {
                    if (!isDeleteActionClick(_event) || !canRef.current.delete) {
                        return;
                    }

                    const row = cell.getRow().getData() as ListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('formTemplates.resource') }),
                        message: t('common.deleteMessage', { name: row.name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    formTemplatesService.destroy(row.id, {
                        preserveScroll: true,
                        onSuccess: () => getTable()?.replaceData(),
                    });
                },
            },
        ];
    }

    return (
        <AppLayout title={t('formTemplates.title')}>
            <Head title={t('formTemplates.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('formTemplates.title')}
                    description={t('formTemplates.descriptionPage')}
                    actions={
                        can.create ? (
                            <Link
                                href={formTemplatesService.createPath}
                                className="inline-flex h-9 items-center gap-2 rounded-lg bg-brand px-3 text-sm font-medium text-white"
                            >
                                <Plus className="size-4" aria-hidden />
                                {t('common.newItem', { resource: t('formTemplates.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<ListItem>
                    ref={tableRef}
                    ajaxURL={formTemplatesService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'id',
                        dir: filters.direction === 'asc' ? 'asc' : 'desc',
                    }}
                    pageSize={Number(filters.per_page) || 12}
                    initialFilters={{
                        search: filters.search,
                        form_type_id: filters.form_type_id,
                        owner_type: filters.owner_type,
                    }}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('formTemplates.searchPlaceholder'),
                        },
                        {
                            type: 'select',
                            name: 'form_type_id',
                            label: t('formTemplates.type'),
                            emptyLabel: t('common.all'),
                            options: typeOptions.map((option) => ({
                                value: String(option.id),
                                label: option.label,
                            })),
                        },
                        {
                            type: 'select',
                            name: 'owner_type',
                            label: t('formTemplates.ownerType'),
                            emptyLabel: t('common.all'),
                            options: ownerTypes.map((value) => ({
                                value,
                                label: t(`formTemplates.ownerTypes.${value}`),
                            })),
                        },
                    ]}
                    savedFiltersPageKey="form_templates"
                    syncUrlBase={formTemplatesService.indexPath}
                    emptyIcon={<FileStack className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('formTemplates.resourcePlural') })}
                    deps={[can.create, can.update, can.delete]}
                />
            </div>
        </AppLayout>
    );
}
