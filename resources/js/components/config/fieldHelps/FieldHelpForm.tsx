import { useMemo, type FormEvent, type ReactNode } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Toggle } from '@/components/ui/Toggle';

export type FieldHelpTranslationRow = {
    locale: string;
    title: string;
    description: string;
};

export type FieldHelpFormValues = {
    table: string;
    column: string;
    is_active: boolean;
    translations: FieldHelpTranslationRow[];
};

export type FieldHelpSchema = {
    tables: string[];
    columns_by_table: Record<string, string[]>;
};

export type LocaleOption = {
    code: string;
    label: string;
};

type FieldHelpFormProps = {
    mode: 'create' | 'edit';
    values: FieldHelpFormValues;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    schema: FieldHelpSchema;
    locales: LocaleOption[];
    onChange: (patch: Partial<FieldHelpFormValues>) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function FieldHelpForm({
    values,
    errors,
    processing,
    schema,
    locales,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: FieldHelpFormProps) {
    const { t } = useTranslation();

    const columns = useMemo(() => {
        if (!values.table) {
            return [];
        }

        return schema.columns_by_table[values.table] ?? [];
    }, [schema.columns_by_table, values.table]);

    const helpKey = values.table && values.column ? `${values.table}.${values.column}` : '';

    const usedLocales = useMemo(
        () => new Set(values.translations.map((row) => row.locale).filter(Boolean)),
        [values.translations],
    );

    const availableLocales = useMemo(
        () => locales.filter((locale) => !usedLocales.has(locale.code)),
        [locales, usedLocales],
    );

    function updateRow(index: number, patch: Partial<FieldHelpTranslationRow>): void {
        onChange({
            translations: values.translations.map((row, rowIndex) =>
                rowIndex === index ? { ...row, ...patch } : row,
            ),
        });
    }

    function removeRow(index: number): void {
        onChange({
            translations: values.translations.filter((_, rowIndex) => rowIndex !== index),
        });
    }

    function addRow(): void {
        const nextLocale = availableLocales[0]?.code ?? '';
        if (!nextLocale) {
            return;
        }

        onChange({
            translations: [
                ...values.translations,
                { locale: nextLocale, title: '', description: '' },
            ],
        });
    }

    function localeChoicesForRow(index: number): LocaleOption[] {
        const current = values.translations[index]?.locale;

        return locales.filter(
            (locale) => locale.code === current || !usedLocales.has(locale.code),
        );
    }

    return (
        <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <div className="grid gap-5 sm:grid-cols-2">
                <Field label={t('fieldHelps.table')} htmlFor="table" error={errors.table} required helpField={false}>
                    <Select
                        id="table"
                        value={values.table}
                        invalid={Boolean(errors.table)}
                        onChange={(event) => {
                            onChange({
                                table: event.target.value,
                                column: '',
                            });
                        }}
                    >
                        <option value="">{t('common.select')}</option>
                        {schema.tables.map((table) => (
                            <option key={table} value={table}>
                                {table}
                            </option>
                        ))}
                    </Select>
                </Field>

                <Field label={t('fieldHelps.column')} htmlFor="column" error={errors.column} required helpField={false}>
                    <Select
                        id="column"
                        value={values.column}
                        invalid={Boolean(errors.column)}
                        disabled={!values.table}
                        onChange={(event) => onChange({ column: event.target.value })}
                    >
                        <option value="">{t('common.select')}</option>
                        {columns.map((column) => (
                            <option key={column} value={column}>
                                {column}
                            </option>
                        ))}
                    </Select>
                </Field>
            </div>

            <Field label={t('fieldHelps.key')} htmlFor="key" error={errors.key} helpField={false}>
                <Input id="key" value={helpKey} readOnly disabled placeholder={t('fieldHelps.keyPlaceholder')} />
                <p className="mt-1 text-xs text-muted">{t('fieldHelps.keyHint')}</p>
            </Field>

            <div className="flex items-center gap-3">
                <span className="text-sm font-semibold text-ink">{t('common.active')}</span>
                <Toggle helpField={false}
                    checked={values.is_active}
                    onCheckedChange={(checked) => onChange({ is_active: checked })}
                    aria-label={t('common.active')}
                />
            </div>

            {errors.translations ? <p className="text-sm text-danger">{errors.translations}</p> : null}

            <div className="space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h3 className="text-sm font-semibold text-ink">{t('fieldHelps.translations')}</h3>
                    <Button
                        type="button"
                        variant="secondary"
                        disabled={availableLocales.length === 0}
                        onClick={addRow}
                    >
                        <Plus className="size-4" aria-hidden />
                        {t('fieldHelps.addTranslation')}
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border border-line">
                    <table className="min-w-full text-left text-sm">
                        <thead className="bg-canvas text-ink-muted">
                            <tr>
                                <th className="px-3 py-2 font-semibold">{t('fieldHelps.language')}</th>
                                <th className="px-3 py-2 font-semibold">{t('fieldHelps.helpTitle')}</th>
                                <th className="px-3 py-2 font-semibold">{t('fieldHelps.helpDescription')}</th>
                                <th className="px-3 py-2 font-semibold">
                                    <span className="sr-only">{t('common.actions')}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {values.translations.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="px-3 py-4 text-muted">
                                        {t('fieldHelps.noTranslations')}
                                    </td>
                                </tr>
                            ) : (
                                values.translations.map((row, index) => (
                                    <tr key={`${row.locale}-${index}`} className="border-t border-line align-top">
                                        <td className="px-3 py-2">
                                            <Select
                                                aria-label={t('fieldHelps.language')}
                                                value={row.locale}
                                                invalid={Boolean(errors[`translations.${index}.locale`])}
                                                onChange={(event) => updateRow(index, { locale: event.target.value })}
                                            >
                                                <option value="">{t('common.select')}</option>
                                                {localeChoicesForRow(index).map((locale) => (
                                                    <option key={locale.code} value={locale.code}>
                                                        {locale.label}
                                                    </option>
                                                ))}
                                            </Select>
                                            {errors[`translations.${index}.locale`] ? (
                                                <p className="mt-1 text-xs text-danger">
                                                    {errors[`translations.${index}.locale`]}
                                                </p>
                                            ) : null}
                                        </td>
                                        <td className="px-3 py-2">
                                            <Input
                                                aria-label={t('fieldHelps.helpTitle')}
                                                value={row.title}
                                                invalid={Boolean(errors[`translations.${index}.title`])}
                                                onChange={(event) => updateRow(index, { title: event.target.value })}
                                            />
                                        </td>
                                        <td className="px-3 py-2">
                                            <textarea
                                                aria-label={t('fieldHelps.helpDescription')}
                                                rows={2}
                                                value={row.description}
                                                onChange={(event) =>
                                                    updateRow(index, { description: event.target.value })
                                                }
                                                className="w-full min-w-[14rem] rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"
                                            />
                                        </td>
                                        <td className="px-3 py-2">
                                            <Button
                                                type="button"
                                                variant="danger"
                                                aria-label={t('common.remove', { label: t('fieldHelps.language') })}
                                                onClick={() => removeRow(index)}
                                            >
                                                <Trash2 className="size-4" aria-hidden />
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="flex flex-wrap items-center justify-end gap-2 border-t border-line pt-4">
                {actions}
                <Button type="submit" loading={processing}>
                    {submitIcon}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
