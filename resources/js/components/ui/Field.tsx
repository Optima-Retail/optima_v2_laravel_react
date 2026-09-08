import type { PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';
import { FieldHelp } from '@/components/ui/FieldHelp';
import { resolveFieldHelpKey, useFieldHelpScope } from '@/components/field-help/FieldHelpScope';
import { cn } from '@/support/cn';

type FieldProps = PropsWithChildren<{
    label: string;
    htmlFor: string;
    error?: string;
    className?: string;
    required?: boolean;
    /**
     * Explicit help key (e.g. companies.tax_id), or false to disable.
     * When omitted, uses `{helpTable|scope}.{htmlFor}` so new DB entries appear automatically.
     */
    helpField?: string | false;
    /** Override FieldHelpScope table for this field only. */
    helpTable?: string;
}>;

export function Field({
    label,
    htmlFor,
    error,
    className,
    required = false,
    helpField,
    helpTable,
    children,
}: FieldProps) {
    const { t } = useTranslation();
    const scope = useFieldHelpScope();
    const resolvedHelp = resolveFieldHelpKey(helpField, htmlFor, helpTable, scope?.table);

    return (
        <div className={cn('space-y-1', className)}>
            <label htmlFor={htmlFor} className="mb-1.5 inline-flex items-center gap-0 text-sm font-semibold text-ink">
                {label}
                {required ? (
                    <span className="ml-0.5 text-danger" title={t('common.required')} aria-hidden>
                        *
                    </span>
                ) : null}
                {required ? <span className="sr-only"> ({t('common.required')})</span> : null}
                {resolvedHelp ? <FieldHelp field={resolvedHelp} /> : null}
            </label>
            {children}
            {error ? <p className="text-sm text-danger">{error}</p> : null}
        </div>
    );
}
