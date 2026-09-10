import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Briefcase, Plus } from 'lucide-react';
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
import { jobTitlesService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
} from '@/support/tabulator';
import type { JobTitleListItem } from '@/support/types/domain/job-title';

type JobTitlesIndexProps = {
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

export default function JobTitlesIndex({ filters, can }: JobTitlesIndexProps) {
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
                title: t('common.code'),
                field: 'code',
                minWidth: 120,
                headerSort: true,
                titleFormatter,
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: 104,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const jobTitle = cell.getRow().getData() as JobTitleListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                jobTitlesService.editPath(jobTitle.id),
                                t('common.editItem', { name: jobTitle.name }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(
                            tabulatorDeleteButton(t('common.deleteItem', { name: jobTitle.name })),
                        );
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const jobTitle = cell.getRow().getData() as JobTitleListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('jobTitles.resource') }),
                        message: t('common.deleteMessage', { name: jobTitle.name }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    jobTitlesService.destroy(jobTitle.id, {
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
        <AppLayout title={t('jobTitles.title')}>
            <Head title={t('jobTitles.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('jobTitles.title')}
                    description={t('jobTitles.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={jobTitlesService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('common.newItem', { resource: t('jobTitles.resource') })}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<JobTitleListItem>
                    ref={tableRef}
                    ajaxURL={jobTitlesService.dataPath}
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
                            placeholder: t('jobTitles.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={jobTitlesService.indexPath}
                    emptyIcon={<Briefcase className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('jobTitles.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
