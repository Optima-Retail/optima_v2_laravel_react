import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import type { TimezoneOption } from '@/support/types/domain';

export type CountryFormValues = {
    name: string;
    iso_code: string;
    timezone_id: string;
};

type CountryFormProps = {
    mode: 'create' | 'edit';
    values: CountryFormValues;
    errors: Partial<Record<keyof CountryFormValues, string>>;
    processing: boolean;
    timezoneOptions: TimezoneOption[];
    onChange: (key: keyof CountryFormValues, value: string) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function CountryForm({
    values,
    errors,
    processing,
    timezoneOptions,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: CountryFormProps) {
    const { t } = useTranslation();

    return (
        <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <div className="grid gap-5 sm:grid-cols-2">
                <Field label={t('common.name')} htmlFor="name" error={errors.name} className="sm:col-span-2" required>
                    <Input
                        id="name"
                        value={values.name}
                        invalid={Boolean(errors.name)}
                        onChange={(event) => onChange('name', event.target.value)}
                    />
                </Field>

                <Field label={t('countries.isoCode')} htmlFor="iso_code" error={errors.iso_code}>
                    <Input
                        id="iso_code"
                        value={values.iso_code}
                        placeholder={t('countries.isoPlaceholder')}
                        maxLength={2}
                        invalid={Boolean(errors.iso_code)}
                        onChange={(event) => onChange('iso_code', event.target.value.toUpperCase())}
                    />
                </Field>

                <Field label={t('countries.timezone')} htmlFor="timezone_id" error={errors.timezone_id}>
                    <SearchableSelect
                        id="timezone_id"
                        value={values.timezone_id}
                        invalid={Boolean(errors.timezone_id)}
                        onChange={(timezoneId) => onChange('timezone_id', timezoneId)}
                        emptyLabel={t('countries.noTimezone')}
                        options={timezoneOptions.map((option) => ({
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
    );
}
