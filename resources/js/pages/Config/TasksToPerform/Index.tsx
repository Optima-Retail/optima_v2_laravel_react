import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ClipboardList, Plus } from 'lucide-react';
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
import { tasksToPerformService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorBadge,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { TaskToPerformListItem } from '@/support/types/domain/task-to-perform';

type TasksToPerformIndexProps = {
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

export default function TasksToPerformIndex({ filters, can }: TasksToPerformIndexProps) {
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
                title: t('common.title'),
                field: 'title',
                minWidth: 180,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('tasksToPerform.documentType'),
                field: 'document_type',
                minWidth: 140,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const value = String(cell.getValue() ?? '');

                    return t(`tasksToPerform.documentTypes.${value}`, { defaultValue: value });
                },
            },
            {
                title: t('tasksToPerform.documentId'),
                field: 'document_id',
                width: 120,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() ?? t('common.emDash'),
            },
            {
                title: t('tasksToPerform.isCompleted'),
                field: 'is_completed',
                width: 130,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const done = Boolean(cell.getValue());

                    return tabulatorBadge(
                        done ? t('tasksToPerform.completed') : t('tasksToPerform.pending'),
                        done ? 'success' : 'neutral',
                    );
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
                    const task = cell.getRow().getData() as TaskToPerformListItem;
                    const name = task.title || String(task.id);
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                tasksToPerformService.editPath(task.id),
                                t('common.editItem', { name }),
                            ),
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
                    const task = cell.getRow().getData() as TaskToPerformListItem;
                    const name = task.title || String(task.id);
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('tasksToPerform.resource') }),
                        message: t('common.deleteMessage', { name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    tasksToPerformService.destroy(task.id, {
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
        <AppLayout title={t('tasksToPerform.title')}>
            <Head title={t('tasksToPerform.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('tasksToPerform.title')}
                    description={t('tasksToPerform.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={tasksToPerformService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.newItem', { resource: t('tasksToPerform.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<TaskToPerformListItem>
                    ref={tableRef}
                    ajaxURL={tasksToPerformService.dataPath}
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
                            placeholder: t('tasksToPerform.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={tasksToPerformService.indexPath}
                    emptyIcon={<ClipboardList className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('tasksToPerform.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
