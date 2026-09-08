import { createContext, useContext, type PropsWithChildren } from 'react';

type FieldHelpScopeValue = {
    /** DB table / domain prefix, e.g. companies → keys like companies.name */
    table: string;
};

const FieldHelpScopeContext = createContext<FieldHelpScopeValue | null>(null);

export function FieldHelpScope({ table, children }: PropsWithChildren<FieldHelpScopeValue>) {
    return <FieldHelpScopeContext.Provider value={{ table }}>{children}</FieldHelpScopeContext.Provider>;
}

export function useFieldHelpScope(): FieldHelpScopeValue | null {
    return useContext(FieldHelpScopeContext);
}

/**
 * Resolve a field-help key.
 * - `helpField` string wins
 * - `false` disables help
 * - otherwise `{table}.{column}` from prop/scope + column (htmlFor / name)
 */
export function resolveFieldHelpKey(
    helpField: string | false | undefined,
    column: string | undefined,
    tableOverride?: string | null,
    scopeTable?: string | null,
): string | null {
    if (helpField === false) {
        return null;
    }

    if (typeof helpField === 'string' && helpField.trim() !== '') {
        return helpField.trim();
    }

    const table = (tableOverride ?? scopeTable ?? '').trim();
    const field = (column ?? '').trim();

    if (table === '' || field === '') {
        return null;
    }

    return `${table}.${field}`;
}
