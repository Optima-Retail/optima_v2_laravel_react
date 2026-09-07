export type SortDirection = 'asc' | 'desc';

export function nextSortState(
    currentSort: string,
    currentDirection: string,
    column: string,
): { sort: string; direction: SortDirection } {
    if (currentSort === column) {
        return {
            sort: column,
            direction: currentDirection === 'asc' ? 'desc' : 'asc',
        };
    }

    return { sort: column, direction: 'asc' };
}
