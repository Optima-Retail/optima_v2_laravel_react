import type { SelectOption } from '@/components/ui/SearchableSelect';
import type { CompanyOption } from '@/support/types/domain/common';

/** Map company options to SearchableSelect / MultiSelect options with logos. */
export function toCompanySelectOptions(options: CompanyOption[]): SelectOption[] {
    return options.map((option) => ({
        value: String(option.id),
        label: option.label,
        logo_url: option.logo_url ?? null,
    }));
}
