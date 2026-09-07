import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { Toggle } from '@/components/ui/Toggle';
import type { UserOption } from '@/support/types/domain';

export type EstablishmentFormValues = {
    company_id: string;
    name: string;
    code: string;
    address_line_1: string;
    address_line_2: string;
    city: string;
    province: string;
    postal_code: string;
    country_id: string;
    timezone_id: string;
    delegation_id: string;
    is_active: boolean;
    billing_company_id: string;
};

type EstablishmentFormProps = {
    values: EstablishmentFormValues;
    errors: Partial<Record<keyof EstablishmentFormValues, string>>;
    processing: boolean;
    companyOptions: UserOption[];
    countryOptions: UserOption[];
    timezoneOptions: UserOption[];
    delegationOptions: UserOption[];
    onChange: (key: keyof EstablishmentFormValues, value: string | boolean) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function EstablishmentForm({
    values,
    errors,
    processing,
    companyOptions,
    countryOptions,
    timezoneOptions,
    delegationOptions,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: EstablishmentFormProps) {
    const { t } = useTranslation();

    return (
        <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <div className="grid gap-5 sm:grid-cols-2">
                <Field label={t('establishments.client')} htmlFor="company_id" error={errors.company_id} className="sm:col-span-2" required>
                    <SearchableSelect
                        id="company_id"
                        value={values.company_id}
                        invalid={Boolean(errors.company_id)}
                        onChange={(value) => onChange('company_id', value)}
                        emptyLabel={t('common.select')}
                        options={companyOptions.map((option) => ({
                            value: String(option.id),
                            label: option.label,
                        }))}
                    />
                </Field>

                <Field label={t('common.name')} htmlFor="name" error={errors.name} required>
                    <Input
                        id="name"
                        value={values.name}
                        invalid={Boolean(errors.name)}
                        onChange={(event) => onChange('name', event.target.value)}
                    />
                </Field>

                <Field label={t('common.code')} htmlFor="code" error={errors.code}>
                    <Input
                        id="code"
                        value={values.code}
                        invalid={Boolean(errors.code)}
                        onChange={(event) => onChange('code', event.target.value)}
                    />
                </Field>

                <Field label={t('companies.addressLine1')} htmlFor="address_line_1" error={errors.address_line_1} className="sm:col-span-2">
                    <Input
                        id="address_line_1"
                        value={values.address_line_1}
                        invalid={Boolean(errors.address_line_1)}
                        onChange={(event) => onChange('address_line_1', event.target.value)}
                    />
                </Field>

                <Field label={t('companies.addressLine2')} htmlFor="address_line_2" error={errors.address_line_2} className="sm:col-span-2">
                    <Input
                        id="address_line_2"
                        value={values.address_line_2}
                        invalid={Boolean(errors.address_line_2)}
                        onChange={(event) => onChange('address_line_2', event.target.value)}
                    />
                </Field>

                <Field label={t('companies.city')} htmlFor="city" error={errors.city}>
                    <Input
                        id="city"
                        value={values.city}
                        invalid={Boolean(errors.city)}
                        onChange={(event) => onChange('city', event.target.value)}
                    />
                </Field>

                <Field label={t('companies.province')} htmlFor="province" error={errors.province}>
                    <Input
                        id="province"
                        value={values.province}
                        invalid={Boolean(errors.province)}
                        onChange={(event) => onChange('province', event.target.value)}
                    />
                </Field>

                <Field label={t('companies.postalCode')} htmlFor="postal_code" error={errors.postal_code}>
                    <Input
                        id="postal_code"
                        value={values.postal_code}
                        invalid={Boolean(errors.postal_code)}
                        onChange={(event) => onChange('postal_code', event.target.value)}
                    />
                </Field>

                <Field label={t('companies.country')} htmlFor="country_id" error={errors.country_id}>
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

                <Field label={t('establishments.timezone')} htmlFor="timezone_id" error={errors.timezone_id}>
                    <SearchableSelect
                        id="timezone_id"
                        value={values.timezone_id}
                        invalid={Boolean(errors.timezone_id)}
                        onChange={(value) => onChange('timezone_id', value)}
                        emptyLabel={t('common.none')}
                        options={timezoneOptions.map((option) => ({
                            value: String(option.id),
                            label: option.label,
                        }))}
                    />
                </Field>

                <Field label={t('establishments.delegation')} htmlFor="delegation_id" error={errors.delegation_id}>
                    <SearchableSelect
                        id="delegation_id"
                        value={values.delegation_id}
                        invalid={Boolean(errors.delegation_id)}
                        onChange={(value) => onChange('delegation_id', value)}
                        emptyLabel={t('common.none')}
                        options={delegationOptions.map((option) => ({
                            value: String(option.id),
                            label: option.label,
                        }))}
                    />
                </Field>

                <Field label={t('establishments.billingCompany')} htmlFor="billing_company_id" error={errors.billing_company_id} className="sm:col-span-2">
                    <SearchableSelect
                        id="billing_company_id"
                        value={values.billing_company_id}
                        invalid={Boolean(errors.billing_company_id)}
                        onChange={(value) => onChange('billing_company_id', value)}
                        emptyLabel={t('common.none')}
                        options={companyOptions.map((option) => ({
                            value: String(option.id),
                            label: option.label,
                        }))}
                    />
                </Field>

                <div className="sm:col-span-2">
                    <Toggle
                        checked={values.is_active}
                        onCheckedChange={(checked) => onChange('is_active', checked)}
                        checkedLabel={t('common.active')}
                        uncheckedLabel={t('common.inactive')}
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
    );
}
