import type { ButtonHTMLAttributes } from 'react';
import { useTranslation } from 'react-i18next';
import { FieldHelp } from '@/components/ui/FieldHelp';
import { resolveFieldHelpKey, useFieldHelpScope } from '@/components/field-help/FieldHelpScope';
import { cn } from '@/support/cn';

type ToggleProps = Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'onChange' | 'role'> & {
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    checkedLabel?: string;
    uncheckedLabel?: string;
    /**
     * Explicit help key, or false to disable.
     * When omitted, uses `{helpTable|scope}.{name|id}` so new DB entries appear automatically.
     */
    helpField?: string | false;
    helpTable?: string;
    /** Column segment for auto help key (preferred over id). */
    name?: string;
};

export function Toggle({
    checked,
    onCheckedChange,
    checkedLabel,
    uncheckedLabel,
    helpField,
    helpTable,
    name,
    id,
    className,
    disabled,
    ...props
}: ToggleProps) {
    const { t } = useTranslation();
    const scope = useFieldHelpScope();
    const onLabel = checkedLabel ?? t('toggle.on');
    const offLabel = uncheckedLabel ?? t('toggle.off');
    const stateLabel = checked ? onLabel : offLabel;
    const resolvedHelp = resolveFieldHelpKey(helpField, name ?? id, helpTable, scope?.table);

    return (
        <div className={cn('inline-flex min-h-8 w-fit items-center gap-1.5', className)}>
            <button
                type="button"
                role="switch"
                id={id}
                aria-checked={checked}
                aria-label={props['aria-label'] ?? `${onLabel} / ${offLabel}`}
                disabled={disabled}
                onClick={() => onCheckedChange(!checked)}
                className={cn(
                    'inline-flex min-h-8 w-fit items-center gap-2.5 rounded-lg px-0.5 outline-none transition-opacity',
                    'focus-visible:ring-2 focus-visible:ring-brand/40 focus-visible:ring-offset-2',
                    'disabled:cursor-not-allowed disabled:opacity-50',
                )}
                {...props}
            >
                <span
                    className={cn(
                        'inline-flex h-5 w-9 shrink-0 rounded-full border p-0.5 transition-colors duration-200',
                        checked ? 'border-brand-strong bg-brand' : 'border-line bg-canvas',
                    )}
                    aria-hidden
                >
                    <span
                        className={cn(
                            'size-3.5 rounded-full bg-white shadow-sm transition-transform duration-200',
                            checked ? 'translate-x-3.5' : 'translate-x-0',
                        )}
                    />
                </span>
                <span className="text-sm text-ink-muted">{stateLabel}</span>
            </button>
            {resolvedHelp ? <FieldHelp field={resolvedHelp} /> : null}
        </div>
    );
}
