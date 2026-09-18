import { useCallback, useMemo } from 'react';
import { MultiSelect, type MultiSelectOption } from '@/components/ui/MultiSelect';
import type { SelectOption } from '@/components/ui/SearchableSelect';
import {
    selectOptionsService,
    type SelectOptionsQuery,
    type SelectOptionsResource,
    type SelectOptionRow,
} from '@/services/selectOptions';
import { resolveIncludeIds, toAsyncSelectOptions } from '@/support/selectOptions';

type SeedOption = {
    id: number;
    label: string;
    color?: string | null;
    logo_url?: string | null;
};

type AsyncMultiSelectProps = {
    id?: string;
    resource: SelectOptionsResource;
    value: string[];
    onChange: (value: string[]) => void;
    seedOptions?: SeedOption[];
    queryParams?: Omit<SelectOptionsQuery, 'search' | 'includeIds'>;
    placeholder?: string;
    invalid?: boolean;
    disabled?: boolean;
    className?: string;
    mapOption?: (row: SelectOptionRow) => SelectOption;
    onOptionsLoaded?: (rows: SelectOptionRow[]) => void;
};

export function AsyncMultiSelect({
    id,
    resource,
    value,
    onChange,
    seedOptions = [],
    queryParams = {},
    placeholder,
    invalid = false,
    disabled = false,
    className,
    mapOption,
    onOptionsLoaded,
}: AsyncMultiSelectProps) {
    const includeIds = useMemo(() => resolveIncludeIds(value), [value]);
    const queryKey = JSON.stringify(queryParams);

    const seed = useMemo((): MultiSelectOption[] => {
        if (mapOption) {
            return seedOptions.map((row) =>
                mapOption({
                    id: row.id,
                    label: row.label,
                    color: row.color,
                    logo_url: row.logo_url,
                }),
            );
        }

        return toAsyncSelectOptions(seedOptions);
    }, [mapOption, seedOptions]);

    const loadOptions = useCallback(
        async (query: string): Promise<SelectOption[]> => {
            const parsedParams = JSON.parse(queryKey) as Omit<SelectOptionsQuery, 'search' | 'includeIds'>;
            const rows = await selectOptionsService.fetch(resource, {
                ...parsedParams,
                search: query,
                includeIds,
            });

            onOptionsLoaded?.(rows);

            if (mapOption) {
                return rows.map(mapOption);
            }

            return toAsyncSelectOptions(rows);
        },
        [includeIds, mapOption, onOptionsLoaded, queryKey, resource],
    );

    return (
        <MultiSelect
            id={id}
            value={value}
            onChange={onChange}
            placeholder={placeholder}
            invalid={invalid}
            disabled={disabled}
            className={className}
            seedOptions={seed}
            loadOptions={loadOptions}
        />
    );
}
