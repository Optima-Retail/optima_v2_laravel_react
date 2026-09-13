import { FormEvent, ReactNode } from 'react';
import { RotateCcw, Search } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
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
};

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
}: FilterBarProps) {
    const { t } = useTranslation();
    const applyLabel = submitLabel ?? t('common.apply');
    const clearLabel = resetLabel ?? t('common.reset');

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        onSubmit();
    }

    const hasActiveFilters = fields.some((field) => (values[field.name] ?? '').trim() !== '');

    return (
        <form
            onSubmit={handleSubmit}
            className={cn('rounded-2xl border border-line bg-surface p-3 sm:p-4', className)}
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
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

                <div className="flex shrink-0 flex-wrap items-center justify-end gap-2">
                    {onReset && hasActiveFilters ? (
                        <Button type="button" variant="ghost" onClick={onReset}>
                            <RotateCcw className="size-3.5" aria-hidden />
                            {clearLabel}
                        </Button>
                    ) : null}
                    {actions}
                    <Button type="submit" variant="secondary">
                        {applyLabel}
                    </Button>
                </div>
            </div>
        </form>
    );
}
