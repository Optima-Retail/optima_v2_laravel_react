import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Plus, Sparkles } from 'lucide-react';
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
import { complimentsService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';

type ComplimentListItem = {
    id: number;
    subject_type: string;
    subject_label: string;
    type_name: string | null;
    comment: string | null;
    score: number | null;
    users: string;
    created_at: string | null;
};

type ComplimentsIndexProps = {
    filters: {
        search: string;
        subject_type: string;
        compliment_type_id: string;
        created_from: string;
        created_to: string;
        sort: string;
        direction: string;
        per_page: string;
    };
    typeOptions: Array<{ id: number; label: string }>;
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
};

const subjectTypes = ['brand', 'customer', 'establishment'] as const;

export default function ComplimentsIndex({ filters, typeOptions, can }: ComplimentsIndexProps) {
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
                title: t('compliments.subject'),
                field: 'subject_label',
                minWidth: 180,
                headerSort: false,
                cssClass: 'cell-strong',
                formatter: (cell: CellComponent) => {
                    const row = cell.getRow().getData() as ComplimentListItem;
                    const subject = row.subject_label || t('common.emDash');
                    const typeLabel = t(`compliments.subjectTypes.${row.subject_type}`, {
                        defaultValue: row.subject_type,
                    });

                    return `<span>${subject}</span><div class="text-xs text-ink-muted">${typeLabel}</div>`;
                },
            },
            {
                title: t('compliments.type'),
                field: 'type_name',
                minWidth: 120,
                headerSort: false,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('compliments.users'),
                field: 'users',
                minWidth: 160,
                headerSort: false,
                cssClass: 'cell-muted',
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
            },
            {
                title: t('compliments.score'),
                field: 'score',
                width: 90,
                headerSort: false,
                formatter: (cell: CellComponent) => cell.getValue() ?? t('common.emDash'),
            },
            {
                title: t('compliments.comment'),
                field: 'comment',
                minWidth: 180,
                headerSort: true,
                cssClass: 'cell-muted',
                titleFormatter,
                formatter: (cell: CellComponent) => cell.getValue() || t('common.emDash'),
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
                    const compliment = cell.getRow().getData() as ComplimentListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                complimentsService.editPath(compliment.id),
                                t('common.editItem', {
                                    name: compliment.subject_label || compliment.id,
                                }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(
                            tabulatorDeleteButton(
                                t('common.deleteItem', {
                                    name: compliment.subject_label || compliment.id,
                                }),
                            ),
                        );
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event) || !canRef.current.delete) {
                        return;
                    }

                    event.preventDefault();
                    const compliment = cell.getRow().getData() as ComplimentListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('compliments.resource') }),
                        message: t('common.deleteMessage', {
                            name: compliment.subject_label || compliment.id,
                        }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    complimentsService.destroy(compliment.id, {
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
        <AppLayout title={t('compliments.title')}>
            <Head title={t('compliments.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('compliments.title')}
                    description={t('compliments.descriptionPage')}
                    actions={
                        can.create ? (
                            <Link
                                href={complimentsService.createPath}
                                className="inline-flex h-9 items-center gap-2 rounded-lg bg-brand px-3 text-sm font-medium text-white"
                            >
                                <Plus className="size-4" aria-hidden />
                                {t('common.newItem', { resource: t('compliments.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<ComplimentListItem>
                    ref={tableRef}
                    ajaxURL={complimentsService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'id',
                        dir: filters.direction === 'asc' ? 'asc' : 'desc',
                    }}
                    pageSize={Number(filters.per_page) || 12}
                    initialFilters={{
                        search: filters.search,
                        subject_type: filters.subject_type,
                        compliment_type_id: filters.compliment_type_id,
                        created_from: filters.created_from,
                        created_to: filters.created_to,
                    }}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('compliments.searchPlaceholder'),
                        },
                        {
                            type: 'select',
                            name: 'subject_type',
                            label: t('filters.subjectType'),
                            emptyLabel: t('common.all'),
                            options: subjectTypes.map((subjectType) => ({
                                value: subjectType,
                                label: t(`compliments.subjectTypes.${subjectType}`),
                            })),
                        },
                        {
                            type: 'select',
                            name: 'compliment_type_id',
                            label: t('filters.type'),
                            emptyLabel: t('common.all'),
                            options: typeOptions.map((option) => ({
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
                    savedFiltersPageKey="compliments"
                    syncUrlBase={complimentsService.indexPath}
                    emptyIcon={<Sparkles className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('compliments.resourcePlural') })}
                    deps={[i18n.language, can.create, can.update, can.delete]}
                />
            </div>
        </AppLayout>
    );
}
