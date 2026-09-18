import { useCallback, useMemo } from 'react';
import { SearchableSelect, type SelectOption } from '@/components/ui/SearchableSelect';
import { useSharedProps } from '@/hooks/useAuth';
import { companiesService, type CompanyOptionsScope } from '@/services/companies';
import { toCompanySelectOptions } from '@/support/companySelect';
import type { CompanyOption } from '@/support/types/domain/common';

type CompanySearchableSelectProps = {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    scope?: CompanyOptionsScope;
    /** Seed the currently selected company so edit forms show a label before open. */
    seedOptions?: CompanyOption[];
    emptyLabel?: string;
    invalid?: boolean;
    disabled?: boolean;
    className?: string;
    exceptId?: number | null;
    includeId?: number | null;
};

export function CompanySearchableSelect({
    id,
    value,
    onChange,
    scope = 'party',
    seedOptions = [],
    emptyLabel,
    invalid = false,
    disabled = false,
    className,
    exceptId,
    includeId,
}: CompanySearchableSelectProps) {
    const { auth } = useSharedProps();
    const resolvedExceptId = exceptId ?? (scope === 'party' ? (auth.company?.id ?? null) : null);
    const parsedValue = value !== '' ? Number(value) : null;
    const resolvedIncludeId =
        includeId ?? (parsedValue !== null && Number.isFinite(parsedValue) && parsedValue > 0 ? parsedValue : null);

    const seed = useMemo(() => toCompanySelectOptions(seedOptions), [seedOptions]);

    const loadOptions = useCallback(
        async (query: string): Promise<SelectOption[]> => {
            const rows = await companiesService.options({
                search: query,
                scope,
                exceptId: resolvedExceptId,
                includeId: resolvedIncludeId,
            });

            return toCompanySelectOptions(rows);
        },
        [scope, resolvedExceptId, resolvedIncludeId],
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
