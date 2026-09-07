import {
    forwardRef,
    useCallback,
    useEffect,
    useImperativeHandle,
    useMemo,
    useRef,
    useState,
    type DependencyList,
    type ReactNode,
} from 'react';
import { useTranslation } from 'react-i18next';
import { TabulatorFull as Tabulator } from 'tabulator-tables';
import type { ColumnDefinition, Options } from 'tabulator-tables';
import { FilterBar, type FilterField } from '@/components/page/FilterBar';
import { Pagination } from '@/components/page/Pagination';
import { LoadingOverlay } from '@/components/ui/LoadingOverlay';
import { replaceListQueryUrl } from '@/services/shared';
import { cn } from '@/support/cn';
import type { Paginated } from '@/support/pagination';
import {
    buildPaginationLinks,
    createSortTitleFormatter,
    emptyPaginator,
    tabulatorAjaxConfig,
    type RemoteSort,
    type TabulatorListResponse,
} from '@/support/tabulator';

export const REMOTE_PAGE_SIZE_OPTIONS = [10, 12, 25, 50, 100] as const;
const EMPTY_DEPS: DependencyList = [];

export type RemoteDataTableHandle = {
    replaceData: () => void;
    setPage: (page: number) => void;
    setPageSize: (size: number) => void;
    getTable: () => Tabulator | null;
    setFilters: (filters: Record<string, string>) => void;
    getFilters: () => Record<string, string>;
};

export type RemoteDataColumnHelpers = {
    titleFormatter: NonNullable<ColumnDefinition['titleFormatter']>;
    getTable: () => Tabulator | null;
};

export type RemoteQueryState = Record<string, string>;

type RemoteDataTableProps = {
    ajaxURL: string;
    /** Extra ajax params merged with active filter values. */
    ajaxParams?: () => Record<string, unknown>;
    columns: (helpers: RemoteDataColumnHelpers) => ColumnDefinition[];
    initialSort?: RemoteSort;
    pageSize?: number;
    filterFields?: FilterField[];
    initialFilters?: Record<string, string>;
    /** Called when filters, page size, or sort change. Prefer URL-only sync — do not refetch list data here. */
    onQueryChange?: (query: RemoteQueryState) => void;
    /** If set, sync query string via history.replaceState (no network request). */
    syncUrlBase?: string;
    emptyIcon?: ReactNode;
    emptyMessage: string;
    loadingLabel?: string;
    className?: string;
    /** Rebuild the table when these change (e.g. locale). */
    deps?: DependencyList;
    options?: Partial<Options>;
};

function activeSorterField(sorter: { field?: string; column?: { getField?: () => string } }): string {
    if (typeof sorter.field === 'string' && sorter.field !== '') {
        return sorter.field;
    }

    return sorter.column?.getField?.() ?? '';
}

function serializeQuery(query: RemoteQueryState): string {
    return JSON.stringify(query);
}

function buildQueryState(
    filters: Record<string, string>,
    pageSize: number,
    sort: string,
    direction: string,
): RemoteQueryState {
    return {
        ...filters,
        per_page: String(pageSize),
        sort,
        direction,
    };
}

function RemoteDataTableInner<T = unknown>(
    {
        ajaxURL,
        ajaxParams,
        columns,
        initialSort,
        pageSize = 12,
        filterFields,
        initialFilters = {},
        onQueryChange,
        syncUrlBase,
        emptyIcon,
        emptyMessage,
        loadingLabel,
        className,
        deps = EMPTY_DEPS,
        options,
    }: RemoteDataTableProps,
    ref: React.ForwardedRef<RemoteDataTableHandle>,
) {
    const { t, i18n } = useTranslation();
    const tableHostRef = useRef<HTMLDivElement>(null);
    const tabulatorRef = useRef<Tabulator | null>(null);
    const ajaxParamsRef = useRef(ajaxParams);
    const columnsRef = useRef(columns);
    const optionsRef = useRef(options);
    const pageSizeRef = useRef(pageSize);
    const initialSortRef = useRef(initialSort);
    const onQueryChangeRef = useRef(onQueryChange);
    const syncUrlBaseRef = useRef(syncUrlBase);
    const filterValuesRef = useRef(initialFilters);
    const allowQueryEmitRef = useRef(false);
    const lastEmittedQueryRef = useRef(
        serializeQuery(
            buildQueryState(
                initialFilters,
                pageSize,
                initialSort?.column ?? '',
                initialSort?.dir ?? 'asc',
            ),
        ),
    );

    const [filterDraft, setFilterDraft] = useState<Record<string, string>>(initialFilters);
    const [filterValues, setFilterValues] = useState<Record<string, string>>(initialFilters);
    const [paginator, setPaginator] = useState<Paginated<T>>(() => emptyPaginator(pageSize));
    const [hasRows, setHasRows] = useState(true);
    const [loading, setLoading] = useState(true);

    ajaxParamsRef.current = ajaxParams;
    columnsRef.current = columns;
    optionsRef.current = options;
    pageSizeRef.current = pageSize;
    initialSortRef.current = initialSort;
    onQueryChangeRef.current = onQueryChange;
    syncUrlBaseRef.current = syncUrlBase;
    filterValuesRef.current = filterValues;

    const getTable = useCallback(() => tabulatorRef.current, []);

    /** One remote request: setPage(1) already loads; avoid also calling replaceData(). */
    const reloadTable = useCallback(() => {
        const table = tabulatorRef.current;

        if (!table) {
            return;
        }

        if (table.getPage() === 1) {
            void table.replaceData();

            return;
        }

        void table.setPage(1);
    }, []);

    const emitQueryChange = useCallback((patch: RemoteQueryState = {}) => {
        const table = tabulatorRef.current;
        const sorter = table?.getSorters()?.[0];
        const pageSizeValue = table?.getPageSize?.();
        const size = typeof pageSizeValue === 'number' ? pageSizeValue : pageSizeRef.current;

        const next = buildQueryState(
            filterValuesRef.current,
            size,
            sorter ? activeSorterField(sorter) : (initialSortRef.current?.column ?? ''),
            sorter?.dir ?? initialSortRef.current?.dir ?? 'asc',
        );
        Object.assign(next, patch);

        const serialized = serializeQuery(next);

        if (serialized === lastEmittedQueryRef.current) {
            return;
        }

        lastEmittedQueryRef.current = serialized;

        if (syncUrlBaseRef.current) {
            replaceListQueryUrl(syncUrlBaseRef.current, next);
        }

        onQueryChangeRef.current?.(next);
    }, []);

    useImperativeHandle(
        ref,
        () => ({
            replaceData: () => {
                void tabulatorRef.current?.replaceData();
            },
            setPage: (page: number) => {
                void tabulatorRef.current?.setPage(page);
            },
            setPageSize: (size: number) => {
                void tabulatorRef.current?.setPageSize(size);
            },
            getTable,
            setFilters: (next) => {
                setFilterDraft(next);
                setFilterValues(next);
                filterValuesRef.current = next;
                reloadTable();
            },
            getFilters: () => ({ ...filterValuesRef.current }),
        }),
        [getTable, reloadTable],
    );

    useEffect(() => {
        if (!tableHostRef.current) {
            return;
        }

        allowQueryEmitRef.current = false;

        const titleFormatter = createSortTitleFormatter(getTable);
        const helpers: RemoteDataColumnHelpers = {
            titleFormatter,
            getTable,
        };

        const table = new Tabulator(tableHostRef.current, {
            ajaxURL,
            ajaxConfig: tabulatorAjaxConfig,
            ajaxParams: () => ({
                ...ajaxParamsRef.current?.(),
                ...filterValuesRef.current,
            }),
            ajaxResponse: (_url, params, response: TabulatorListResponse<T>) => {
                const page = Number((params as { page?: number | string }).page ?? 1);
                const size = Number(
                    (params as { size?: number | string }).size ?? pageSizeRef.current ?? 12,
                );
                const total = response.last_row ?? 0;
                const from = total === 0 ? null : (page - 1) * size + 1;
                const to = total === 0 ? null : Math.min(page * size, total);
                const lastPage = Math.max(1, response.last_page ?? 1);

                setHasRows(total > 0);
                setPaginator({
                    data: response.data,
                    current_page: page,
                    last_page: lastPage,
                    per_page: size,
                    total,
                    from,
                    to,
                    links: buildPaginationLinks(page, lastPage),
                });

                return {
                    data: response.data,
                    last_page: lastPage,
                    last_row: total,
                };
            },
            layout: 'fitColumns',
            reactiveData: false,
            height: 'auto',
            headerVisible: true,
            pagination: true,
            paginationMode: 'remote',
            paginationSize: pageSizeRef.current || 12,
            sortMode: 'remote',
            filterMode: 'remote',
            initialSort: initialSortRef.current
                ? [
                      {
                          column: initialSortRef.current.column,
                          dir: initialSortRef.current.dir,
                      },
                  ]
                : undefined,
            placeholder: ' ',
            dataLoader: false,
            selectableRows: false,
            columns: columnsRef.current(helpers),
            ...optionsRef.current,
        });

        table.on('dataLoading', () => {
            setLoading(true);
        });

        table.on('dataLoaded', () => {
            setLoading(false);
            // Enable URL sync only after bootstrap load (prevents extra /companies Inertia visit on entry).
            window.setTimeout(() => {
                allowQueryEmitRef.current = true;
            }, 0);
        });

        table.on('dataLoadError', () => {
            setLoading(false);
        });

        table.on('dataSorted', () => {
            table.redraw(true);

            if (allowQueryEmitRef.current) {
                emitQueryChange();
            }
        });

        tableHostRef.current.classList.add('app-tabulator');
        tabulatorRef.current = table;
        setLoading(true);

        return () => {
            allowQueryEmitRef.current = false;
            table.destroy();
            tabulatorRef.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps -- deps provided by caller
    }, [ajaxURL, getTable, emitQueryChange, i18n.language, ...deps]);

    const showEmpty = useMemo(() => !hasRows && !loading, [hasRows, loading]);

    function applyFilters() {
        setFilterValues(filterDraft);
        filterValuesRef.current = filterDraft;
        emitQueryChange(filterDraft);
        reloadTable();
    }

    function resetFilters() {
        const cleared: Record<string, string> = {};

        for (const field of filterFields ?? []) {
            cleared[field.name] = '';
        }

        setFilterDraft(cleared);
        setFilterValues(cleared);
        filterValuesRef.current = cleared;
        emitQueryChange(cleared);
        reloadTable();
    }

    function visitPage(url: string) {
        const page = Number(url);

        if (!Number.isFinite(page) || page < 1) {
            return;
        }

        void tabulatorRef.current?.setPage(page);
    }

    function changePerPage(perPage: string) {
        const size = Number(perPage) || 12;
        const table = tabulatorRef.current;

        pageSizeRef.current = size;
        emitQueryChange({ per_page: String(size) });

        // Tabulator's public setPageSize already sets size and loads page 1 — do not also replaceData/setPage.
        table?.setPageSize(size);
    }

    return (
        <div className={cn('space-y-6', className)}>
            {filterFields && filterFields.length > 0 ? (
                <FilterBar
                    values={filterDraft}
                    onChange={(name, value) =>
                        setFilterDraft((current) => ({ ...current, [name]: value }))
                    }
                    onSubmit={applyFilters}
                    onReset={resetFilters}
                    fields={filterFields}
                />
            ) : null}

            <LoadingOverlay
                show={loading}
                className="overflow-hidden rounded-2xl border border-line bg-surface"
                label={loadingLabel ?? t('common.loading')}
            >
                <div className={showEmpty ? 'hidden' : 'overflow-x-auto'}>
                    <div ref={tableHostRef} />
                </div>
                {showEmpty ? (
                    <div className="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                        {emptyIcon ? (
                            <span className="flex size-12 items-center justify-center rounded-2xl bg-brand-soft text-brand">
                                {emptyIcon}
                            </span>
                        ) : null}
                        <p className="font-display text-lg font-semibold text-ink">{emptyMessage}</p>
                    </div>
                ) : null}
                {loading && !hasRows ? <div className="min-h-48" aria-hidden /> : null}
            </LoadingOverlay>

            {!showEmpty ? (
                <Pagination
                    paginator={paginator}
                    onVisit={visitPage}
                    perPage={String(paginator.per_page ?? pageSize)}
                    onPerPageChange={changePerPage}
                />
            ) : null}
        </div>
    );
}

export const RemoteDataTable = forwardRef(RemoteDataTableInner) as <T = unknown>(
    props: RemoteDataTableProps & { ref?: React.Ref<RemoteDataTableHandle> },
) => ReturnType<typeof RemoteDataTableInner<T>>;
