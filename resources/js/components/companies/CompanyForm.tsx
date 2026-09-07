import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { Select } from '@/components/ui/Select';
import { Toggle } from '@/components/ui/Toggle';
import type { UserOption } from '@/support/types/domain';

export type CompanyFormValues = {
    name: string;
    tradename: string;
    tax_id: string;
    kind: string;
    country_id: string;
    residence_country_id: string;
    person_type: string;
    email: string;
    phone: string;
    website: string;
    address_line_1: string;
    address_line_2: string;
    city: string;
    province: string;
    postal_code: string;
    employee_count: string;
    is_active: boolean;
    brand_id: string;
};

type CompanyFormProps = {
    values: CompanyFormValues;
    errors: Partial<Record<keyof CompanyFormValues, string>>;
    processing: boolean;
    countryOptions: UserOption[];
    brandOptions: UserOption[];
    onChange: (key: keyof CompanyFormValues, value: string | boolean) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

const kinds = ['operating_company', 'corporation', 'holding', 'ute', 'party'] as const;
const personTypes = ['F', 'J'] as const;

export function CompanyForm({
    values,
    errors,
    processing,
    countryOptions,
    brandOptions,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: CompanyFormProps) {
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

                <Field label={t('companies.tradename')} htmlFor="tradename" error={errors.tradename}>
                    <Input
                        id="tradename"
                        value={values.tradename}
                        invalid={Boolean(errors.tradename)}
                        onChange={(event) => onChange('tradename', event.target.value)}
                    />
                </Field>

                <Field label={t('companies.taxId')} htmlFor="tax_id" error={errors.tax_id}>
                    <Input
                        id="tax_id"
                        value={values.tax_id}
                        invalid={Boolean(errors.tax_id)}
                        onChange={(event) => onChange('tax_id', event.target.value)}
                    />
                </Field>

                <Field label={t('companies.kind')} htmlFor="kind" error={errors.kind} required>
                    <Select
                        id="kind"
                        value={values.kind}
                        invalid={Boolean(errors.kind)}
                        onChange={(event) => onChange('kind', event.target.value)}
                    >
                        {kinds.map((kind) => (
                            <option key={kind} value={kind}>
                                {t(`companies.kinds.${kind}`)}
                            </option>
                        ))}
                    </Select>
                </Field>

                <Field label={t('companies.personType')} htmlFor="person_type" error={errors.person_type}>
                    <Select
                        id="person_type"
                        value={values.person_type}
                        invalid={Boolean(errors.person_type)}
                        onChange={(event) => onChange('person_type', event.target.value)}
                    >
                        <option value="">{t('common.none')}</option>
                        {personTypes.map((type) => (
                            <option key={type} value={type}>
                                {t(`companies.personTypes.${type}`)}
                            </option>
                        ))}
                    </Select>
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

                <Field label={t('companies.residenceCountry')} htmlFor="residence_country_id" error={errors.residence_country_id}>
                    <SearchableSelect
                        id="residence_country_id"
                        value={values.residence_country_id}
                        invalid={Boolean(errors.residence_country_id)}
                        onChange={(value) => onChange('residence_country_id', value)}
                        emptyLabel={t('common.none')}
                        options={countryOptions.map((option) => ({
                            value: String(option.id),
                            label: option.label,
                        }))}
                    />
                </Field>

                <Field label={t('common.email')} htmlFor="email" error={errors.email}>
                    <Input
                        id="email"
                        type="email"
                        value={values.email}
                        invalid={Boolean(errors.email)}
                        onChange={(event) => onChange('email', event.target.value)}
                    />
                </Field>

                <Field label={t('companies.phone')} htmlFor="phone" error={errors.phone}>
                    <Input
                        id="phone"
                        value={values.phone}
                        invalid={Boolean(errors.phone)}
                        onChange={(event) => onChange('phone', event.target.value)}
                    />
                </Field>

                <Field label={t('companies.website')} htmlFor="website" error={errors.website} className="sm:col-span-2">
                    <Input
                        id="website"
                        value={values.website}
                        invalid={Boolean(errors.website)}
                        onChange={(event) => onChange('website', event.target.value)}
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

                <Field label={t('companies.employeeCount')} htmlFor="employee_count" error={errors.employee_count}>
                    <Input
                        id="employee_count"
                        type="number"
                        min={0}
                        value={values.employee_count}
                        invalid={Boolean(errors.employee_count)}
                        onChange={(event) => onChange('employee_count', event.target.value)}
                    />
                </Field>

                <Field label={t('companies.brand')} htmlFor="brand_id" error={errors.brand_id}>
                    <SearchableSelect
                        id="brand_id"
                        value={values.brand_id}
                        invalid={Boolean(errors.brand_id)}
                        onChange={(value) => onChange('brand_id', value)}
                        emptyLabel={t('common.none')}
                        options={brandOptions.map((option) => ({
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
