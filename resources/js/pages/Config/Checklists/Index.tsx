import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ListChecks, Plus } from 'lucide-react';
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
import { checklistsService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
    tabulatorStatusBadge,
} from '@/support/tabulator';
import type { ChecklistListItem } from '@/support/types/domain/checklist';

type ChecklistsIndexProps = {
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

export default function ChecklistsIndex({ filters, can }: ChecklistsIndexProps) {
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
                title: t('checklists.label'),
                field: 'label',
                minWidth: 220,
                headerSort: true,
                titleFormatter,
            },
            {
                title: t('checklists.documentType'),
                field: 'document_type',
                width: 140,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const value = String(cell.getValue() ?? '');

                    return t(`checklists.documentTypes.${value}`, { defaultValue: value });
                },
            },
            {
                title: t('checklists.status'),
                field: 'status_label',
                minWidth: 160,
                headerSort: false,
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as ChecklistListItem;
                    const label = row.status_label || t('common.emDash');

                    if (!row.status_color) {
                        return label;
                    }

                    return `<span class="inline-flex items-center gap-2"><span class="inline-block size-3.5 rounded border border-line" style="background-color: ${row.status_color}"></span>${label}</span>`;
                },
            },
            {
                title: t('checklists.sortOrder'),
                field: 'sort_order',
                width: 110,
                headerSort: true,
                titleFormatter,
            },
            {
                title: t('checklists.requiresValidation'),
                field: 'requires_validation',
                width: 140,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) =>
                    tabulatorStatusBadge(
                        Boolean(cell.getValue()),
                        t('checklists.requiresValidation'),
                        t('checklists.requiresValidationOff'),
                    ),
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: 104,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const checklist = cell.getRow().getData() as ChecklistListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                checklistsService.editPath(checklist.id),
                                t('common.editItem', { name: checklist.label }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(tabulatorDeleteButton(t('common.deleteItem', { name: checklist.label })));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const checklist = cell.getRow().getData() as ChecklistListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('checklists.resource') }),
                        message: t('common.deleteMessage', { name: checklist.label }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    checklistsService.destroy(checklist.id, {
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
        <AppLayout title={t('checklists.title')}>
            <Head title={t('checklists.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('checklists.title')}
                    description={t('checklists.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={checklistsService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('checklists.new')}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<ChecklistListItem>
                    ref={tableRef}
                    ajaxURL={checklistsService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'sort_order',
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
                            placeholder: t('checklists.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={checklistsService.indexPath}
                    emptyIcon={<ListChecks className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('checklists.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
