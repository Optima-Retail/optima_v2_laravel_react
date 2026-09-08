import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import type { TabulatorFull as Tabulator } from 'tabulator-tables';
import { buildPageWindow } from '@/helpers/pagination';
import type { Paginated, PaginationLink } from '@/support/pagination';

export type TabulatorListResponse<T> = {
    data: T[];
    last_page: number;
    last_row: number;
};

export type RemoteSort = {
    column: string;
    dir: 'asc' | 'desc';
};

export const tabulatorAjaxConfig = {
    method: 'GET' as const,
    credentials: 'same-origin' as const,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
};

const sortIconAsc =
    '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 shrink-0" aria-hidden="true"><path d="m5 12 7-7 7 7"/><path d="M12 19V5"/></svg>';
const sortIconDesc =
    '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 shrink-0" aria-hidden="true"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>';
const sortIconIdle =
    '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 shrink-0 opacity-50" aria-hidden="true"><path d="m21 16-4 4-4-4"/><path d="M17 20V4"/><path d="m3 8 4-4 4 4"/><path d="M7 4v16"/></svg>';

const editIcon =
    '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>';
const deleteIcon =
    '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>';

export function emptyPaginator<T>(perPage: number): Paginated<T> {
    return {
        data: [],
        current_page: 1,
        last_page: 1,
        per_page: perPage,
        total: 0,
        from: null,
        to: null,
        links: [],
    };
}

export function buildPaginationLinks(current: number, last: number): PaginationLink[] {
    const links: PaginationLink[] = [
        {
            url: current > 1 ? String(current - 1) : null,
            label: '&laquo; Previous',
            active: false,
        },
    ];

    for (const item of buildPageWindow(current, last)) {
        if (item === 'ellipsis') {
            links.push({
                url: null,
                label: '...',
                active: false,
            });
            continue;
        }

        links.push({
            url: String(item),
            label: String(item),
            active: item === current,
        });
    }

    links.push({
        url: current < last ? String(current + 1) : null,
        label: 'Next &raquo;',
        active: false,
    });

    return links;
}

export function createSortTitleFormatter(
    getTable: () => Tabulator | null,
): NonNullable<ColumnDefinition['titleFormatter']> {
    return (cell: CellComponent) => {
        const column = cell.getColumn();
        const field = column.getField();
        const sorters = getTable()?.getSorters() ?? [];
        const active = sorters.find((sorter) => {
            const sorterField =
                'field' in sorter && typeof sorter.field === 'string'
                    ? sorter.field
                    : sorter.column?.getField?.();

            return sorterField === field;
        });
        const label = column.getDefinition().title ?? '';
        const icon = active ? (active.dir === 'asc' ? sortIconAsc : sortIconDesc) : sortIconIdle;
        const colorClass = active ? 'text-ink' : 'text-ink-muted';

        return `<span class="inline-flex items-center gap-1 uppercase tracking-[0.08em] ${colorClass}">${label}${icon}</span>`;
    };
}

const mapPinIcon =
    '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>';

export function tabulatorEditLink(href: string, ariaLabel: string): string {
    return `<a href="${href}" class="inline-flex size-8 items-center justify-center rounded-lg border border-line text-ink-muted transition-colors hover:border-brand/40 hover:text-brand" aria-label="${ariaLabel}" data-action="edit">${editIcon}</a>`;
}

export function tabulatorDeleteButton(ariaLabel: string): string {
    return `<button type="button" class="inline-flex size-8 items-center justify-center rounded-lg border border-line text-ink-muted transition-colors hover:border-danger/40 hover:text-danger" aria-label="${ariaLabel}" data-action="delete">${deleteIcon}</button>`;
}

export function tabulatorProvincesButton(ariaLabel: string): string {
    return `<button type="button" class="inline-flex size-8 items-center justify-center rounded-lg border border-line text-ink-muted transition-colors hover:border-brand/40 hover:text-brand" aria-label="${ariaLabel}" data-action="provinces">${mapPinIcon}</button>`;
}

export function tabulatorActionsCell(parts: string[]): string {
    return `<div class="flex items-center justify-end gap-1.5">${parts.join('')}</div>`;
}

export function isDeleteActionClick(event: UIEvent): boolean {
    return isActionClick(event, 'delete');
}

export function isActionClick(event: UIEvent, action: string): boolean {
    const target = event.target as HTMLElement | null;

    return Boolean(target?.closest(`[data-action="${action}"]`));
}
