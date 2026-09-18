import { useCallback, useMemo } from 'react';
import { SearchableSelect, type SelectOption } from '@/components/ui/SearchableSelect';
import {
    selectOptionsService,
    type SelectOptionsQuery,
    type SelectOptionsResource,
    type SelectOptionRow,
} from '@/services/selectOptions';
import { resolveIncludeId, toAsyncSelectOptions } from '@/support/selectOptions';

type SeedOption = {
    id: number;
    label: string;
    color?: string | null;
    logo_url?: string | null;
};

type AsyncSearchableSelectProps = {
    id?: string;
    resource: SelectOptionsResource;
    value: string;
    onChange: (value: string) => void;
    seedOptions?: SeedOption[];
    queryParams?: Omit<SelectOptionsQuery, 'search' | 'includeId'>;
    emptyLabel?: string;
    invalid?: boolean;
    disabled?: boolean;
    className?: string;
    mapOption?: (row: SelectOptionRow) => SelectOption;
    onOptionsLoaded?: (rows: SelectOptionRow[]) => void;
};

export function AsyncSearchableSelect({
    id,
    resource,
    value,
    onChange,
    seedOptions = [],
    queryParams = {},
    emptyLabel,
    invalid = false,
    disabled = false,
    className,
    mapOption,
    onOptionsLoaded,
}: AsyncSearchableSelectProps) {
    const includeId = resolveIncludeId(value);
    const queryKey = JSON.stringify(queryParams);

    const seed = useMemo(() => {
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
            const parsedParams = JSON.parse(queryKey) as Omit<SelectOptionsQuery, 'search' | 'includeId'>;
            const rows = await selectOptionsService.fetch(resource, {
                ...parsedParams,
                search: query,
                includeId,
            });

            onOptionsLoaded?.(rows);

            if (mapOption) {
                return rows.map(mapOption);
            }

            return toAsyncSelectOptions(rows);
        },
        [includeId, mapOption, onOptionsLoaded, queryKey, resource],
    );

    return (
        <SearchableSelect
            id={id}
            value={value}
            onChange={onChange}
            emptyLabel={emptyLabel}
            invalid={invalid}
            disabled={disabled}
            className={className}
            seedOptions={seed}
            loadOptions={loadOptions}
        />
    );
}
