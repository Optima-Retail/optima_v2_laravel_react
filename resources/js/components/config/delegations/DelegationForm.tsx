import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { Toggle } from '@/components/ui/Toggle';
import type { UserOption } from '@/support/types/domain';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';

export type DelegationFormValues = {
    name: string;
    tax_id: string;
    company_id: string;
    address: string;
    currency_id: string;
    country_id: string;
    series_id: string;
    cost_includes_vat: boolean;
    recovers_vat: boolean;
    billing_info: string;
};

type DelegationFormProps = {
    values: DelegationFormValues;
    errors: Partial<Record<keyof DelegationFormValues, string>>;
    processing: boolean;
    companyOptions: UserOption[];
    currencyOptions: UserOption[];
    countryOptions: UserOption[];
    seriesOptions: UserOption[];
    onChange: (key: keyof DelegationFormValues, value: string | boolean) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function DelegationForm({
    values,
    errors,
    processing,
    companyOptions,
    currencyOptions,
    countryOptions,
    seriesOptions,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: DelegationFormProps) {
    const { t } = useTranslation();

    return (
        <FieldHelpScope table="delegations">
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

                <Field label={t('delegations.taxId')} htmlFor="tax_id" error={errors.tax_id}>
                    <Input
                        id="tax_id"
                        value={values.tax_id}
                        invalid={Boolean(errors.tax_id)}
                        onChange={(event) => onChange('tax_id', event.target.value)}
                    />
                </Field>

                <Field label={t('delegations.company')} htmlFor="company_id" error={errors.company_id}>
                    <SearchableSelect
                        id="company_id"
                        value={values.company_id}
                        invalid={Boolean(errors.company_id)}
                        onChange={(value) => onChange('company_id', value)}
                        emptyLabel={t('common.none')}
                        options={companyOptions.map((option) => ({
                            value: String(option.id),
                            label: option.label,
                        }))}
                    />
                </Field>

                <Field label={t('delegations.currency')} htmlFor="currency_id" error={errors.currency_id}>
                    <SearchableSelect
                        id="currency_id"
                        value={values.currency_id}
                        invalid={Boolean(errors.currency_id)}
                        onChange={(value) => onChange('currency_id', value)}
                        emptyLabel={t('common.none')}
                        options={currencyOptions.map((option) => ({
                            value: String(option.id),
                            label: option.label,
                        }))}
                    />
                </Field>

                <Field label={t('delegations.country')} htmlFor="country_id" error={errors.country_id}>
                    <SearchableSelect
                        id="country_id"
                        value={values.country_id}
                        invalid={Boolean(errors.country_id)}
                        onChange={(value) => onChange('country_id', value)}
                        emptyLabel={t('common.none')}
                        options={countryOptions.map((option) => ({
                            value: String(option.id),
                            label: option.label,
                        }))}
                    />
                </Field>

                <Field label={t('delegations.series')} htmlFor="series_id" error={errors.series_id}>
                    <SearchableSelect
                        id="series_id"
                        value={values.series_id}
                        invalid={Boolean(errors.series_id)}
                        onChange={(value) => onChange('series_id', value)}
                        emptyLabel={t('common.none')}
                        options={seriesOptions.map((option) => ({
                            value: String(option.id),
                            label: option.label,
                        }))}
                    />
                </Field>

                <Field label={t('delegations.address')} htmlFor="address" error={errors.address} className="sm:col-span-2">
                    <Input
                        id="address"
                        value={values.address}
                        invalid={Boolean(errors.address)}
                        onChange={(event) => onChange('address', event.target.value)}
                    />
                </Field>

                <Field label={t('delegations.billingInfo')} htmlFor="billing_info" error={errors.billing_info} className="sm:col-span-2">
                    <Input
                        id="billing_info"
                        value={values.billing_info}
                        placeholder={t('delegations.billingInfoPlaceholder')}
                        invalid={Boolean(errors.billing_info)}
                        onChange={(event) => onChange('billing_info', event.target.value)}
                    />
                </Field>

                <div>
                    <Toggle
                        checked={values.cost_includes_vat}
                        onCheckedChange={(checked) => onChange('cost_includes_vat', checked)}
                        checkedLabel={t('delegations.costIncludesVat')}
                        uncheckedLabel={t('delegations.costExcludesVat')}
                    />
                </div>

                <div>
                    <Toggle
                        checked={values.recovers_vat}
                        onCheckedChange={(checked) => onChange('recovers_vat', checked)}
                        checkedLabel={t('delegations.recoversVat')}
                        uncheckedLabel={t('delegations.doesNotRecoverVat')}
                    />
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
        </FieldHelpScope>
    );
}
