import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ClipboardPen, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import { PageHeader } from '@/components/page/PageHeader';
import {
    RemoteDataTable,
    type RemoteDataColumnHelpers,
    type RemoteDataTableHandle,
} from '@/components/table/RemoteDataTable';
import { confirmAction } from '@/helpers/confirm';
import { copyText } from '@/helpers/clipboard';
import { AppLayout } from '@/layouts/AppLayout';
import { formsService } from '@/services';
import {
    isCopyActionClick,
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorCopyButton,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import { useToastStore } from '@/stores/toastStore';

type ListItem = {
    id: number;
    public_id: string;
    public_url?: string;
    name: string | null;
    type_name: string | null;
    status_name: string | null;
    subject_type: string;
    subject_label: string;
    occurred_on: string | null;
    created_at: string | null;
};

type IndexProps = {
    filters: {
        search: string;
        form_type_id: string;
        form_status_id: string;
        subject_type: string;
        sort: string;
        direction: string;
        per_page: string;
    };
    typeOptions: Array<{ id: number; label: string }>;
    statusOptions: Array<{ id: number; label: string }>;
    can: { create: boolean; update: boolean; delete: boolean };
};

export default function FormsIndex({ filters, typeOptions, statusOptions, can }: IndexProps) {
    const { t } = useTranslation();
    const pushToast = useToastStore((state) => state.push);
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
                minWidth: 180,
                headerSort: true,
                cssClass: 'cell-strong',
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as ListItem;

                    return row.name || row.public_id || t('common.emDash');
                },
                titleFormatter,
            },
            {
                title: t('forms.subject'),
                field: 'subject_label',
                minWidth: 160,
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as ListItem;
                    const typeLabel = t(`forms.subjectTypes.${row.subject_type}`, {
                        defaultValue: row.subject_type,
                    });

                    return `<span>${row.subject_label || t('common.emDash')}</span><div class="text-xs text-ink-muted">${typeLabel}</div>`;
                },
            },
            {
                title: t('forms.type'),
                field: 'type_name',
                minWidth: 110,
                headerSort: false,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('forms.status'),
                field: 'status_name',
                minWidth: 110,
                headerSort: false,
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
                    const row = cell.getRow().getData() as ListItem;
                    const parts: string[] = [
                        tabulatorCopyButton(t('forms.copyPublicLink')),
                    ];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                formsService.editPath(row.id),
                                t('common.editItem', { name: row.name || row.id }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(tabulatorDeleteButton(t('common.delete')));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event, cell) => {
                    const row = cell.getRow().getData() as ListItem;

                    if (isCopyActionClick(event)) {
                        const url = row.public_url || formsService.publicUrl(row.public_id);
                        const ok = await copyText(url);
                        pushToast(
                            ok ? t('forms.publicLinkCopied') : t('forms.publicLinkCopyFailed'),
                            ok ? 'success' : 'error',
                        );

                        return;
                    }

                    if (!isDeleteActionClick(event) || !canRef.current.delete) {
                        return;
                    }

                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('forms.resource') }),
                        message: t('common.deleteMessage', { name: row.name || row.id }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    formsService.destroy(row.id, {
                        preserveScroll: true,
                        onSuccess: () => getTable()?.replaceData(),
                    });
                },
            },
        ];
    }

    return (
        <AppLayout title={t('forms.title')}>
            <Head title={t('forms.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('forms.title')}
                    description={t('forms.descriptionPage')}
                    actions={
                        can.create ? (
                            <Link
                                href={formsService.createPath}
                                className="inline-flex h-9 items-center gap-2 rounded-lg bg-brand px-3 text-sm font-medium text-white"
                            >
                                <Plus className="size-4" aria-hidden />
                                {t('common.newItem', { resource: t('forms.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<ListItem>
                    ref={tableRef}
                    ajaxURL={formsService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'id',
                        dir: filters.direction === 'asc' ? 'asc' : 'desc',
                    }}
                    pageSize={Number(filters.per_page) || 12}
                    initialFilters={{
                        search: filters.search,
                        form_type_id: filters.form_type_id,
                        form_status_id: filters.form_status_id,
                        subject_type: filters.subject_type,
                    }}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('forms.searchPlaceholder'),
                        },
                        {
                            type: 'select',
                            name: 'form_type_id',
                            label: t('forms.type'),
                            emptyLabel: t('common.all'),
                            options: typeOptions.map((option) => ({
                                value: String(option.id),
                                label: option.label,
                            })),
                        },
                        {
                            type: 'select',
                            name: 'form_status_id',
                            label: t('forms.status'),
                            emptyLabel: t('common.all'),
                            options: statusOptions.map((option) => ({
                                value: String(option.id),
                                label: option.label,
                            })),
                        },
                        {
                            type: 'select',
                            name: 'subject_type',
                            label: t('forms.subjectType'),
                            emptyLabel: t('common.all'),
                            options: [
                                { value: 'work_order', label: t('forms.subjectTypes.work_order') },
                                { value: 'technician', label: t('forms.subjectTypes.technician') },
                            ],
                        },
                    ]}
                    savedFiltersPageKey="forms"
                    syncUrlBase={formsService.indexPath}
                    emptyIcon={<ClipboardPen className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('forms.resourcePlural') })}
                    deps={[can.create, can.update, can.delete]}
                />
            </div>
        </AppLayout>
    );
}
