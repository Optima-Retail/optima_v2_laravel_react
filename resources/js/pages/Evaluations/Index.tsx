import { useEffect, useRef } from 'react';
import { Head } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
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
import { evaluationsService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorColorBadge,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { EvaluationListItem } from '@/support/types/domain/evaluation';

type EvaluationsIndexProps = {
    filters: {
        search: string;
        evaluation_status_id: string;
        created_from: string;
        created_to: string;
        sort: string;
        direction: string;
        per_page: string;
    };
    evaluationStatusOptions: Array<{ id: number; label: string }>;
    can: {
        update: boolean;
        delete: boolean;
    };
};

export default function EvaluationsIndex({ filters, evaluationStatusOptions, can }: EvaluationsIndexProps) {
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
                title: t('evaluations.subject'),
                field: 'subject',
                minWidth: 180,
                headerSort: true,
                cssClass: 'cell-strong',
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('evaluations.establishment'),
                field: 'establishment_name',
                minWidth: 160,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('evaluations.status'),
                field: 'status_name',
                minWidth: 140,
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as EvaluationListItem;
                    const name = row.status_name;

                    if (!name) {
                        return `<span class="text-ink-muted">${t('common.emDash')}</span>`;
                    }

                    return tabulatorColorBadge(name, row.status_color || '#94a3b8');
                },
            },
            {
                title: t('evaluations.responsibleUser'),
                field: 'responsible_user_name',
                minWidth: 140,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('evaluations.nextActionAt'),
                field: 'next_action_at',
                minWidth: 160,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('evaluations.visitCount'),
                field: 'visit_count',
                width: 100,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
            },
            {
                title: t('evaluations.callCount'),
                field: 'call_count',
                width: 100,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
            },
            {
                title: t('common.createdAt'),
                field: 'created_at',
                minWidth: 140,
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
                    const evaluation = cell.getRow().getData() as EvaluationListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                evaluationsService.editPath(evaluation.id),
                                t('common.editItem', {
                                    name: evaluation.subject || evaluation.id,
                                }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(
                            tabulatorDeleteButton(
                                t('common.deleteItem', {
                                    name: evaluation.subject || evaluation.id,
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
                    const evaluation = cell.getRow().getData() as EvaluationListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('evaluations.resource') }),
                        message: t('common.deleteMessage', {
                            name: evaluation.subject || evaluation.id,
                        }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    evaluationsService.destroy(evaluation.id, {
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
        <AppLayout title={t('evaluations.title')}>
            <Head title={t('evaluations.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('evaluations.title')}
                    description={t('evaluations.descriptionPage')}
                />

                <RemoteDataTable<EvaluationListItem>
                    ref={tableRef}
                    ajaxURL={evaluationsService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'id',
                        dir: filters.direction === 'asc' ? 'asc' : 'desc',
                    }}
                    pageSize={Number(filters.per_page) || 12}
                    initialFilters={{
                        search: filters.search,
                        evaluation_status_id: filters.evaluation_status_id,
                        created_from: filters.created_from,
                        created_to: filters.created_to,
                    }}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('evaluations.searchPlaceholder'),
                        },
                        {
                            type: 'select',
                            name: 'evaluation_status_id',
                            label: t('filters.status'),
                            emptyLabel: t('common.all'),
                            options: evaluationStatusOptions.map((option) => ({
                                value: String(option.id),
                                label: option.label,
                            })),
                        },
                        {
                            type: 'date',
                            name: 'created_from',
                            label: t('filters.createdFrom'),
                        },
                        {
                            type: 'date',
                            name: 'created_to',
                            label: t('filters.createdTo'),
                        },
                    ]}
                    savedFiltersPageKey="evaluations"
                    syncUrlBase={evaluationsService.indexPath}
                    emptyIcon={<ClipboardCheck className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('evaluations.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
