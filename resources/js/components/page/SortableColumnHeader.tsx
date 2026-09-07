import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { cn } from '@/support/cn';
import { tableHeadCellClass } from '@/support/table';

type SortableColumnHeaderProps = {
    label: string;
    column: string;
    sort: string;
    direction: string;
    onSort: (column: string) => void;
    className?: string;
};

export function SortableColumnHeader({
    label,
    column,
    sort,
    direction,
    onSort,
    className,
}: SortableColumnHeaderProps) {
    const active = sort === column;

    return (
        <th className={cn(tableHeadCellClass, className)}>
            <button
                type="button"
                onClick={() => onSort(column)}
                className={cn(
                    'inline-flex items-center gap-1 uppercase tracking-[0.08em] transition-colors hover:text-ink',
                    active ? 'text-ink' : 'text-ink-muted',
                )}
            >
                {label}
                {active && direction === 'asc' ? (
                    <ArrowUp className="size-3.5 shrink-0" aria-hidden />
                ) : active && direction === 'desc' ? (
                    <ArrowDown className="size-3.5 shrink-0" aria-hidden />
                ) : (
                    <ArrowUpDown className="size-3.5 shrink-0 opacity-50" aria-hidden />
                )}
            </button>
        </th>
    );
}
