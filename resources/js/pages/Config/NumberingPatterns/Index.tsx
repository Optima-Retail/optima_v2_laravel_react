import { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Hash, Plus } from 'lucide-react';
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
import { numberingPatternsService } from '@/services';
import {
    isDeleteActionClick,
    tabulatorActionsCell,
    tabulatorDeleteButton,
    tabulatorEditLink,
    tabulatorStatusBadge,
} from '@/support/tabulator';
import type { NumberingPatternListItem } from '@/support/types/domain/numbering-pattern';

type NumberingPatternsIndexProps = {
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

export default function NumberingPatternsIndex({ filters, can }: NumberingPatternsIndexProps) {
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
                titleFormatter,
            },
            {
                title: t('numberingPatterns.resource'),
                field: 'resource',
                minWidth: 140,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) => {
                    const resource = String(cell.getValue() ?? '');

                    return t(`numberingPatterns.resources.${resource}`, { defaultValue: resource });
                },
            },
            {
                title: t('numberingPatterns.preview'),
                field: 'preview',
                minWidth: 180,
                headerSort: false,
                formatter: (cell: CellComponent) =>
                    `<span class="font-mono">${cell.getValue() || t('common.emDash')}</span>`,
            },
            {
                title: t('numberingPatterns.resetYearly'),
                field: 'reset_yearly',
                width: 120,
                headerSort: false,
                formatter: (cell: CellComponent) =>
                    cell.getValue() ? t('common.yes') : t('common.no'),
            },
            {
                title: t('common.status'),
                field: 'is_active',
                width: 110,
                headerSort: true,
                titleFormatter,
                formatter: (cell: CellComponent) =>
                    tabulatorStatusBadge(Boolean(cell.getValue()), t('common.active'), t('common.inactive')),
            },
            {
                title: t('common.actions'),
                field: 'actions',
                width: 104,
                hozAlign: 'right',
                headerHozAlign: 'right',
                headerSort: false,
                formatter: (cell: CellComponent) => {
                    const pattern = cell.getRow().getData() as NumberingPatternListItem;
                    const parts: string[] = [];

                    if (canRef.current.update) {
                        parts.push(
                            tabulatorEditLink(
                                numberingPatternsService.editPath(pattern.id),
                                t('common.editItem', { name: pattern.resource }),
                            ),
                        );
                    }

                    if (canRef.current.delete) {
                        parts.push(tabulatorDeleteButton(t('common.deleteItem', { name: pattern.resource })));
                    }

                    return tabulatorActionsCell(parts);
                },
                cellClick: async (event: UIEvent, cell: CellComponent) => {
                    if (!isDeleteActionClick(event)) {
                        return;
                    }

                    event.preventDefault();
                    const pattern = cell.getRow().getData() as NumberingPatternListItem;
                    const confirmed = await confirmAction({
                        title: t('common.deleteTitle', { resource: t('numberingPatterns.resourceSingular') }),
                        message: t('common.deleteMessage', { name: pattern.resource }),
                        confirmLabel: t('common.delete'),
                        tone: 'danger',
                    });

                    if (!confirmed) {
                        return;
                    }

                    numberingPatternsService.destroy(pattern.id, {
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
        <AppLayout title={t('numberingPatterns.title')}>
            <Head title={t('numberingPatterns.title')} />
            <div className="space-y-6">
                <PageHeader
                    title={t('numberingPatterns.title')}
                    description={t('numberingPatterns.description')}
                    actions={
                        can.create ? (
                            <Link
                                href={numberingPatternsService.createPath}
                                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors hover:bg-brand-strong"
                            >
                                <Plus className="size-3.5" aria-hidden />
                                {t('numberingPatterns.new')}
                            </Link>
                        ) : null
                    }
                />

                <RemoteDataTable<NumberingPatternListItem>
                    ref={tableRef}
                    ajaxURL={numberingPatternsService.dataPath}
                    columns={buildColumns}
                    initialSort={{
                        column: filters.sort || 'resource',
                        dir: filters.direction === 'desc' ? 'desc' : 'asc',
                    }}
                    pageSize={Number(filters.per_page) || 12}
                    initialFilters={{ search: filters.search }}
                    filterFields={[
                        {
                            type: 'search',
                            name: 'search',
                            label: t('common.search'),
                            placeholder: t('numberingPatterns.searchPlaceholder'),
                        },
                    ]}
                    syncUrlBase={numberingPatternsService.indexPath}
                    emptyIcon={<Hash className="size-5" aria-hidden />}
                    emptyMessage={t('common.empty', { resource: t('numberingPatterns.resourcePlural') })}
                    deps={[i18n.language]}
                />
            </div>
        </AppLayout>
    );
}
