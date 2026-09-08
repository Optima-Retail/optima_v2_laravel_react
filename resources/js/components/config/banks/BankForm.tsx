import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { Toggle } from '@/components/ui/Toggle';
import type { CountryOption } from '@/support/types/domain';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';

export type BankFormValues = {
    name: string;
    legal_name: string;
    country_id: string;
    swift_bic: string;
    national_bank_code: string;
    lei: string;
    supervisor_code: string;
    website: string;
    is_active: boolean;
};

type BankFormProps = {
    mode: 'create' | 'edit';
    values: BankFormValues;
    errors: Partial<Record<keyof BankFormValues, string>>;
    processing: boolean;
    countryOptions: CountryOption[];
    onChange: (key: keyof BankFormValues, value: string | boolean) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function BankForm({
    values,
    errors,
    processing,
    countryOptions,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: BankFormProps) {
    const { t } = useTranslation();

    return (
        <FieldHelpScope table="banks">
        <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <div className="grid gap-5 sm:grid-cols-2">
                <Field label={t('common.name')} htmlFor="name" error={errors.name} required>
                    <Input
                        id="name"
                        value={values.name}
                        invalid={Boolean(errors.name)}
                        onChange={(event) => onChange('name', event.target.value)}
                    />
                </Field>

                <Field label={t('banks.legalName')} htmlFor="legal_name" error={errors.legal_name}>
                    <Input
                        id="legal_name"
                        value={values.legal_name}
                        invalid={Boolean(errors.legal_name)}
                        onChange={(event) => onChange('legal_name', event.target.value)}
                    />
                </Field>

                <Field label={t('banks.country')} htmlFor="country_id" error={errors.country_id} required>
                    <SearchableSelect
                        id="country_id"
                        value={values.country_id}
                        invalid={Boolean(errors.country_id)}
                        onChange={(countryId) => onChange('country_id', countryId)}
                        emptyLabel={t('banks.selectCountry')}
                        options={countryOptions.map((country) => ({
                            value: String(country.id),
                            label: country.label,
                        }))}
                    />
                </Field>

                <Field label={t('banks.swiftBic')} htmlFor="swift_bic" error={errors.swift_bic}>
                    <Input
                        id="swift_bic"
                        value={values.swift_bic}
                        placeholder={t('banks.swiftPlaceholder')}
                        invalid={Boolean(errors.swift_bic)}
                        onChange={(event) => onChange('swift_bic', event.target.value.toUpperCase())}
                    />
                </Field>

                <Field label={t('banks.nationalBankCode')} htmlFor="national_bank_code" error={errors.national_bank_code}>
                    <Input
                        id="national_bank_code"
                        value={values.national_bank_code}
                        invalid={Boolean(errors.national_bank_code)}
                        onChange={(event) => onChange('national_bank_code', event.target.value)}
                    />
                </Field>

                <Field label={t('banks.lei')} htmlFor="lei" error={errors.lei}>
                    <Input
                        id="lei"
                        value={values.lei}
                        invalid={Boolean(errors.lei)}
                        onChange={(event) => onChange('lei', event.target.value.toUpperCase())}
                    />
                </Field>

                <Field label={t('banks.supervisorCode')} htmlFor="supervisor_code" error={errors.supervisor_code}>
                    <Input
                        id="supervisor_code"
                        value={values.supervisor_code}
                        invalid={Boolean(errors.supervisor_code)}
                        onChange={(event) => onChange('supervisor_code', event.target.value)}
                    />
                </Field>

                <Field label={t('banks.website')} htmlFor="website" error={errors.website} className="sm:col-span-2">
                    <Input
                        id="website"
                        value={values.website}
                        placeholder="https://"
                        invalid={Boolean(errors.website)}
                        onChange={(event) => onChange('website', event.target.value)}
                    />
                </Field>

                <Field label={t('common.status')} htmlFor="is_active">
                    <Toggle
                        id="is_active"
                        checked={values.is_active}
                        onCheckedChange={(checked) => onChange('is_active', checked)}
                        checkedLabel={t('common.active')}
                        uncheckedLabel={t('common.inactive')}
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
