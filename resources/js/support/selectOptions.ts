import type { SelectOption } from '@/components/ui/SearchableSelect';
import type { SelectOptionRow } from '@/services/selectOptions';

/** Map standard `{ id, label, ... }` rows to SearchableSelect / MultiSelect options. */
export function toAsyncSelectOptions(rows: Array<Pick<SelectOptionRow, 'id' | 'label' | 'color' | 'logo_url'>>): SelectOption[] {
    return rows.map((row) => {
        const option: SelectOption = {
            value: String(row.id),
            label: row.label,
        };

        if ('color' in row) {
            option.color = row.color ?? null;
        }

        if ('logo_url' in row) {
            option.logo_url = row.logo_url ?? null;
        }

        return option;
    });
}

export function resolveIncludeId(value: string): number | null {
    if (value === '') {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
}

export function resolveIncludeIds(values: string[]): number[] {
    return values
        .map((value) => Number(value))
        .filter((id): id is number => Number.isFinite(id) && id > 0);
}
