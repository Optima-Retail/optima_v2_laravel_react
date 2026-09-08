import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { Toggle } from '@/components/ui/Toggle';
import type { SeriesOption } from '@/support/types/domain';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';

export type SeriesFormValues = {
    key: string;
    color: string;
    is_selectable: boolean;
    credit_note_series_id: string;
};

type SeriesFormProps = {
    mode: 'create' | 'edit';
    values: SeriesFormValues;
    errors: Partial<Record<keyof SeriesFormValues, string>>;
    processing: boolean;
    seriesOptions: SeriesOption[];
    onChange: (key: keyof SeriesFormValues, value: string | boolean) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function SeriesForm({
    values,
    errors,
    processing,
    seriesOptions,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: SeriesFormProps) {
    const { t } = useTranslation();

    return (
        <FieldHelpScope table="series">
        <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <div className="grid gap-5 sm:grid-cols-2">
                <Field label={t('series.key')} htmlFor="key" error={errors.key} required>
                    <Input
                        id="key"
                        value={values.key}
                        placeholder={t('series.keyPlaceholder')}
                        invalid={Boolean(errors.key)}
                        onChange={(event) => onChange('key', event.target.value.toUpperCase())}
                    />
                </Field>

                <Field label={t('common.color')} htmlFor="color" error={errors.color} required>
                    <div className="flex items-center gap-2">
                        <Input
                            id="color"
                            type="color"
                            value={values.color}
                            className="h-8 w-14 cursor-pointer p-1"
                            invalid={Boolean(errors.color)}
                            onChange={(event) => onChange('color', event.target.value)}
                        />
                        <Input
                            value={values.color}
                            invalid={Boolean(errors.color)}
                            onChange={(event) => onChange('color', event.target.value)}
                        />
                    </div>
                </Field>

                <Field label={t('series.selectable')} htmlFor="is_selectable" error={errors.is_selectable} required>
                    <Toggle
                        id="is_selectable"
                        checked={values.is_selectable}
                        onCheckedChange={(checked) => onChange('is_selectable', checked)}
                        checkedLabel={t('series.selectable')}
                        uncheckedLabel={t('series.notSelectable')}
                    />
                </Field>

                <Field label={t('series.creditNoteSeries')} htmlFor="credit_note_series_id" error={errors.credit_note_series_id}>
                    <SearchableSelect
                        id="credit_note_series_id"
                        value={values.credit_note_series_id}
                        invalid={Boolean(errors.credit_note_series_id)}
                        onChange={(creditNoteSeriesId) => onChange('credit_note_series_id', creditNoteSeriesId)}
                        emptyLabel={t('common.none')}
                        options={seriesOptions.map((option) => ({
                            value: String(option.id),
                            label: option.label,
                        }))}
                    />
                </Field>
            </div>

            <div className="flex flex-wrap items-center justify-end gap-2 border-t border-line pt-4">
                {actions}
                <Button type="submit" loading={processing}>
                    {submitIcon}
                    {submitLabel}
                </Button>
            </div>
        </form>
        </FieldHelpScope>
    );
}
