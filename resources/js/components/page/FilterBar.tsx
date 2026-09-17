import { FormEvent, ReactNode, useId, useState } from 'react';
import { ChevronDown, RotateCcw, Search } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { MultiSelect } from '@/components/ui/MultiSelect';
import { Select } from '@/components/ui/Select';
import { cn } from '@/support/cn';

export type FilterOption = {
    value: string;
    label: string;
};

type FilterFieldBase = {
    name: string;
    label?: string;
    className?: string;
};

export type FilterField =
    | (FilterFieldBase & {
          type: 'search' | 'text' | 'date';
          placeholder?: string;
      })
    | (FilterFieldBase & {
          type: 'select';
          options: FilterOption[];
          emptyLabel?: string;
      })
    | (FilterFieldBase & {
          type: 'multiselect';
          options: FilterOption[];
          placeholder?: string;
      });

type FilterBarProps = {
    fields: FilterField[];
    values: Record<string, string>;
    onChange: (name: string, value: string) => void;
    onSubmit: () => void;
    onReset?: () => void;
    submitLabel?: string;
    resetLabel?: string;
    actions?: ReactNode;
    className?: string;
    /** When true, filters start collapsed behind a toggle (main index tables). */
    collapsible?: boolean;
};

function splitCsv(value: string | undefined): string[] {
    if (!value?.trim()) {
        return [];
    }

    return value
        .split(',')
        .map((part) => part.trim())
        .filter(Boolean);
}

export function FilterBar({
    fields,
    values,
    onChange,
    onSubmit,
    onReset,
    submitLabel,
    resetLabel,
    actions,
    className,
    collapsible = false,
}: FilterBarProps) {
    const { t } = useTranslation();
    const panelId = useId();
    const [open, setOpen] = useState(!collapsible);
    const applyLabel = submitLabel ?? t('common.apply');
    const clearLabel = resetLabel ?? t('common.reset');

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        onSubmit();
    }

    const hasActiveFilters = fields.some((field) => (values[field.name] ?? '').trim() !== '');
    const activeCount = fields.filter((field) => (values[field.name] ?? '').trim() !== '').length;

    const fieldsGrid = (
        <div
            className={cn(
                'grid min-w-0 gap-3',
                fields.length === 1
                    ? 'w-full sm:max-w-sm sm:flex-1'
                    : 'w-full flex-1 sm:grid-cols-2 lg:grid-cols-3',
            )}
        >
            {fields.map((field) => {
                if (field.type === 'search' || field.type === 'text' || field.type === 'date') {
                    return (
                        <div key={field.name} className={cn('min-w-0', field.className)}>
                            {field.label ? (
                                <label
                                    htmlFor={`filter-${field.name}`}
                                    className="mb-1.5 block text-xs font-semibold uppercase tracking-[0.08em] text-ink-muted"
                                >
                                    {field.label}
                                </label>
                            ) : null}
                            <div className="relative">
                                {field.type === 'search' ? (
                                    <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-ink-muted">
                                        <Search className="size-4" aria-hidden />
                                    </span>
                                ) : null}
                                <Input
                                    id={`filter-${field.name}`}
                                    type={field.type === 'date' ? 'date' : 'text'}
                                    value={values[field.name] ?? ''}
                                    onChange={(event) => onChange(field.name, event.target.value)}
                                    placeholder={field.placeholder}
                                    className={field.type === 'search' ? 'pl-10' : undefined}
                                />
                            </div>
                        </div>
                    );
                }

                if (field.type === 'multiselect') {
                    return (
                        <div key={field.name} className={cn('min-w-0', field.className)}>
                            {field.label ? (
                                <label
                                    htmlFor={`filter-${field.name}`}
                                    className="mb-1.5 block text-xs font-semibold uppercase tracking-[0.08em] text-ink-muted"
                                >
                                    {field.label}
                                </label>
                            ) : null}
                            <MultiSelect
                                id={`filter-${field.name}`}
                                options={field.options}
                                value={splitCsv(values[field.name])}
                                placeholder={field.placeholder}
                                onChange={(next) => onChange(field.name, next.join(','))}
                            />
                        </div>
                    );
                }

                if (field.type !== 'select') {
                    return null;
                }

                return (
                    <div key={field.name} className={cn('min-w-0', field.className)}>
                        {field.label ? (
                            <label
                                htmlFor={`filter-${field.name}`}
                                className="mb-1.5 block text-xs font-semibold uppercase tracking-[0.08em] text-ink-muted"
                            >
                                {field.label}
                            </label>
                        ) : null}
                        <Select
                            id={`filter-${field.name}`}
                            value={values[field.name] ?? ''}
                            onChange={(event) => onChange(field.name, event.target.value)}
                        >
                            <option value="">{field.emptyLabel ?? t('common.all')}</option>
                            {field.options.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                    </div>
                );
            })}
        </div>
    );

    const actionRow = (
        <div className="flex shrink-0 flex-wrap items-center justify-end gap-2">
            {onReset && hasActiveFilters ? (
                <Button type="button" variant="ghost" onClick={onReset}>
                    <RotateCcw className="size-3.5" aria-hidden />
                    {clearLabel}
                </Button>
            ) : null}
            {!collapsible ? actions : null}
            <Button type="submit" variant="secondary">
                {applyLabel}
            </Button>
        </div>
    );

    if (!collapsible) {
        return (
            <form
                onSubmit={handleSubmit}
                className={cn('rounded-2xl border border-line bg-surface p-3 sm:p-4', className)}
            >
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    {fieldsGrid}
                    {actionRow}
                </div>
            </form>
        );
    }

    return (
        <div className={cn('rounded-2xl border border-line bg-surface', className)}>
            <div className="flex items-center gap-2 px-3 py-2 sm:px-4">
                <button
                    type="button"
                    aria-expanded={open}
                    aria-controls={panelId}
                    onClick={() => setOpen((current) => !current)}
                    className="flex min-w-0 flex-1 items-center justify-between gap-3 rounded-lg py-1.5 text-left transition-colors hover:bg-canvas/60"
                >
                    <span className="inline-flex min-w-0 items-center gap-2">
                        <span className="text-sm font-semibold text-ink">{t('filters.panelTitle')}</span>
                        {hasActiveFilters ? (
                            <span className="inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-brand-soft text-[11px] font-bold text-brand">
                                {activeCount}
                            </span>
                        ) : null}
                    </span>
                    <span className="inline-flex items-center gap-1.5 text-sm font-medium text-ink-muted">
                        {open ? t('filters.collapse') : t('filters.expand')}
                        <ChevronDown
                            className={cn('size-4 shrink-0 transition-transform', open && 'rotate-180')}
                            aria-hidden
                        />
                    </span>
                </button>

                {actions ? <div className="shrink-0">{actions}</div> : null}
            </div>

            {open ? (
                <form
                    id={panelId}
                    onSubmit={handleSubmit}
                    className="border-t border-line p-3 sm:p-4"
                >
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        {fieldsGrid}
                        {actionRow}
                    </div>
                </form>
            ) : null}
        </div>
    );
}
