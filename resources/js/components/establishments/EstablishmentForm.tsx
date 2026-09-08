import { useMemo, type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { TabPanel, Tabs, type TabItem } from '@/components/ui/Tabs';
import { Toggle } from '@/components/ui/Toggle';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import type { EstablishmentFormData, ProvinceOption, UserOption } from '@/support/types/domain';

const textareaClassName =
    'min-h-20 w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20';

export type EstablishmentFormValues = {
    company_id: string;
    name: string;
    code: string;
    store_code: string;
    alternate_store_code: string;
    phone: string;
    email: string;
    emails: string;
    recipient_emails: string;
    address_line_1: string;
    address_line_2: string;
    city: string;
    province_id: string;
    postal_code: string;
    country_id: string;
    timezone_id: string;
    language_id: string;
    establishment_type_id: string;
    delegation_id: string;
    series_id: string;
    billing_company_id: string;
    responsible_user_id: string;
    is_active: boolean;
    is_client_priority: boolean;
    is_reviewed: boolean;
    is_email_reviewed: boolean;
    has_site_health_and_safety: boolean;
    has_customer_health_and_safety: boolean;
    is_quality_control_contactable: boolean;
    has_parking: boolean;
    is_ulez_zone: boolean;
    latitude: string;
    longitude: string;
    tax_rate: string;
    tax_included: boolean;
    legacy_erp_id: string;
    integration_external_id: string;
    notes: string;
    notes_alert: boolean;
    internal_notes: string;
    internal_notes_alert: boolean;
};

type EstablishmentFormProps = {
    values: EstablishmentFormValues;
    errors: Partial<Record<keyof EstablishmentFormValues, string>>;
    processing: boolean;
    companyOptions: UserOption[];
    countryOptions: UserOption[];
    provinceOptions: ProvinceOption[];
    timezoneOptions: UserOption[];
    delegationOptions: UserOption[];
    languageOptions: UserOption[];
    establishmentTypeOptions: UserOption[];
    seriesOptions: UserOption[];
    userOptions: UserOption[];
    onChange: (key: keyof EstablishmentFormValues, value: string | boolean) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

function id(value: number | null | undefined): string {
    return value ? String(value) : '';
}

function num(value: number | string | null | undefined): string {
    return value === null || value === undefined || value === '' ? '' : String(value);
}

function bool(value: boolean | null | undefined): boolean {
    return Boolean(value);
}

export function defaultEstablishmentFormValues(overrides: Partial<EstablishmentFormValues> = {}): EstablishmentFormValues {
    return {
        company_id: '',
        name: '',
        code: '',
        store_code: '',
        alternate_store_code: '',
        phone: '',
        email: '',
        emails: '',
        recipient_emails: '',
        address_line_1: '',
        address_line_2: '',
        city: '',
        province_id: '',
        postal_code: '',
        country_id: '',
        timezone_id: '',
        language_id: '',
        establishment_type_id: '',
        delegation_id: '',
        series_id: '',
        billing_company_id: '',
        responsible_user_id: '',
        is_active: true,
        is_client_priority: false,
        is_reviewed: false,
        is_email_reviewed: false,
        has_site_health_and_safety: false,
        has_customer_health_and_safety: false,
        is_quality_control_contactable: false,
        has_parking: false,
        is_ulez_zone: false,
        latitude: '',
        longitude: '',
        tax_rate: '',
        tax_included: false,
        legacy_erp_id: '',
        integration_external_id: '',
        notes: '',
        notes_alert: false,
        internal_notes: '',
        internal_notes_alert: false,
        ...overrides,
    };
}

export function establishmentFormValuesFromData(establishment: EstablishmentFormData): EstablishmentFormValues {
    return {
        company_id: String(establishment.company_id),
        name: establishment.name,
        code: establishment.code ?? '',
        store_code: establishment.store_code ?? '',
        alternate_store_code: establishment.alternate_store_code ?? '',
        phone: establishment.phone ?? '',
        email: establishment.email ?? '',
        emails: establishment.emails ?? '',
        recipient_emails: establishment.recipient_emails ?? '',
        address_line_1: establishment.address_line_1 ?? '',
        address_line_2: establishment.address_line_2 ?? '',
        city: establishment.city ?? '',
        province_id: id(establishment.province_id),
        postal_code: establishment.postal_code ?? '',
        country_id: id(establishment.country_id),
        timezone_id: id(establishment.timezone_id),
        language_id: id(establishment.language_id),
        establishment_type_id: id(establishment.establishment_type_id),
        delegation_id: id(establishment.delegation_id),
        series_id: id(establishment.series_id),
        billing_company_id: id(establishment.billing_company_id),
        responsible_user_id: id(establishment.responsible_user_id),
        is_active: establishment.is_active,
        is_client_priority: bool(establishment.is_client_priority),
        is_reviewed: bool(establishment.is_reviewed),
        is_email_reviewed: bool(establishment.is_email_reviewed),
        has_site_health_and_safety: bool(establishment.has_site_health_and_safety),
        has_customer_health_and_safety: bool(establishment.has_customer_health_and_safety),
        is_quality_control_contactable: bool(establishment.is_quality_control_contactable),
        has_parking: bool(establishment.has_parking),
        is_ulez_zone: bool(establishment.is_ulez_zone),
        latitude: establishment.latitude ?? '',
        longitude: establishment.longitude ?? '',
        tax_rate: num(establishment.tax_rate),
        tax_included: bool(establishment.tax_included),
        legacy_erp_id: num(establishment.legacy_erp_id),
        integration_external_id: establishment.integration_external_id ?? '',
        notes: establishment.notes ?? '',
        notes_alert: bool(establishment.notes_alert),
        internal_notes: establishment.internal_notes ?? '',
        internal_notes_alert: bool(establishment.internal_notes_alert),
    };
}

function toSelectOptions(options: UserOption[]) {
    return options.map((option) => ({
        value: String(option.id),
        label: option.label,
    }));
}

export function EstablishmentForm({
    values,
    errors,
    processing,
    companyOptions,
    countryOptions,
    provinceOptions,
    timezoneOptions,
    delegationOptions,
    languageOptions,
    establishmentTypeOptions,
    seriesOptions,
    userOptions,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: EstablishmentFormProps) {
    const { t } = useTranslation();

    const filteredProvinceOptions = useMemo(() => {
        if (!values.country_id) {
            return provinceOptions;
        }

        return provinceOptions.filter((option) => String(option.country_id) === values.country_id);
    }, [provinceOptions, values.country_id]);

    const tabItems = useMemo<TabItem[]>(
        () => [
            { id: 'identity', label: t('establishments.tabs.identity') },
            { id: 'address', label: t('establishments.tabs.address') },
            { id: 'catalogs', label: t('establishments.tabs.catalogs') },
            { id: 'geo', label: t('establishments.tabs.geo') },
            { id: 'notes', label: t('establishments.tabs.notes') },
            { id: 'flags', label: t('establishments.tabs.flags') },
        ],
        [t],
    );

    return (
        <FieldHelpScope table="establishments">
        <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <Tabs items={tabItems} defaultValue="identity">
                <TabPanel id="identity">
                    <div className="grid gap-5 sm:grid-cols-2">
                    <Field label={t('establishments.client')} htmlFor="company_id" error={errors.company_id} className="sm:col-span-2" required>
                        <SearchableSelect
                            id="company_id"
                            value={values.company_id}
                            invalid={Boolean(errors.company_id)}
                            onChange={(value) => onChange('company_id', value)}
                            emptyLabel={t('common.select')}
                            options={toSelectOptions(companyOptions)}
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

                    <Field label={t('establishments.storeCode')} htmlFor="store_code" error={errors.store_code}>
                        <Input
                            id="store_code"
                            value={values.store_code}
                            invalid={Boolean(errors.store_code)}
                            onChange={(event) => onChange('store_code', event.target.value)}
                        />
                    </Field>

                    <Field label={t('establishments.alternateStoreCode')} htmlFor="alternate_store_code" error={errors.alternate_store_code}>
                        <Input
                            id="alternate_store_code"
                            value={values.alternate_store_code}
                            invalid={Boolean(errors.alternate_store_code)}
                            onChange={(event) => onChange('alternate_store_code', event.target.value)}
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

                    <Field label={t('common.email')} htmlFor="email" error={errors.email}>
                        <Input
                            id="email"
                            type="email"
                            value={values.email}
                            invalid={Boolean(errors.email)}
                            onChange={(event) => onChange('email', event.target.value)}
                        />
                    </Field>

                    <Field label={t('establishments.emails')} htmlFor="emails" error={errors.emails} className="sm:col-span-2">
                        <Input
                            id="emails"
                            value={values.emails}
                            invalid={Boolean(errors.emails)}
                            onChange={(event) => onChange('emails', event.target.value)}
                        />
                    </Field>

                    <Field label={t('establishments.recipientEmails')} htmlFor="recipient_emails" error={errors.recipient_emails} className="sm:col-span-2">
                        <Input
                            id="recipient_emails"
                            value={values.recipient_emails}
                            invalid={Boolean(errors.recipient_emails)}
                            onChange={(event) => onChange('recipient_emails', event.target.value)}
                        />
                    </Field>
                    </div>
                </TabPanel>

                <TabPanel id="address">
                    <div className="grid gap-5 sm:grid-cols-2">
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

                    <Field label={t('companies.province')} htmlFor="province_id" error={errors.province_id}>
                        <SearchableSelect
                            id="province_id"
                            value={values.province_id}
                            invalid={Boolean(errors.province_id)}
                            onChange={(value) => onChange('province_id', value)}
                            emptyLabel={t('common.none')}
                            options={filteredProvinceOptions.map((option) => ({
                                value: String(option.id),
                                label: option.label,
                            }))}
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
                    </div>
                </TabPanel>

                <TabPanel id="catalogs">
                    <div className="grid gap-5 sm:grid-cols-2">
                    <Field label={t('companies.country')} htmlFor="country_id" error={errors.country_id}>
                        <SearchableSelect
                            id="country_id"
                            value={values.country_id}
                            invalid={Boolean(errors.country_id)}
                            onChange={(value) => {
                                onChange('country_id', value);
                                const stillValid = provinceOptions.some(
                                    (option) =>
                                        String(option.id) === values.province_id &&
                                        String(option.country_id) === value,
                                );
                                if (values.province_id && !stillValid) {
                                    onChange('province_id', '');
                                }
                            }}
                            emptyLabel={t('common.none')}
                            options={toSelectOptions(countryOptions)}
                        />
                    </Field>

                    <Field label={t('establishments.timezone')} htmlFor="timezone_id" error={errors.timezone_id}>
                        <SearchableSelect
                            id="timezone_id"
                            value={values.timezone_id}
                            invalid={Boolean(errors.timezone_id)}
                            onChange={(value) => onChange('timezone_id', value)}
                            emptyLabel={t('common.none')}
                            options={toSelectOptions(timezoneOptions)}
                        />
                    </Field>

                    <Field label={t('establishments.language')} htmlFor="language_id" error={errors.language_id}>
                        <SearchableSelect
                            id="language_id"
                            value={values.language_id}
                            invalid={Boolean(errors.language_id)}
                            onChange={(value) => onChange('language_id', value)}
                            emptyLabel={t('common.none')}
                            options={toSelectOptions(languageOptions)}
                        />
                    </Field>

                    <Field label={t('establishments.type')} htmlFor="establishment_type_id" error={errors.establishment_type_id}>
                        <SearchableSelect
                            id="establishment_type_id"
                            value={values.establishment_type_id}
                            invalid={Boolean(errors.establishment_type_id)}
                            onChange={(value) => onChange('establishment_type_id', value)}
                            emptyLabel={t('common.none')}
                            options={toSelectOptions(establishmentTypeOptions)}
                        />
                    </Field>

                    <Field label={t('establishments.delegation')} htmlFor="delegation_id" error={errors.delegation_id}>
                        <SearchableSelect
                            id="delegation_id"
                            value={values.delegation_id}
                            invalid={Boolean(errors.delegation_id)}
                            onChange={(value) => onChange('delegation_id', value)}
                            emptyLabel={t('common.none')}
                            options={toSelectOptions(delegationOptions)}
                        />
                    </Field>

                    <Field label={t('establishments.series')} htmlFor="series_id" error={errors.series_id}>
                        <SearchableSelect
                            id="series_id"
                            value={values.series_id}
                            invalid={Boolean(errors.series_id)}
                            onChange={(value) => onChange('series_id', value)}
                            emptyLabel={t('common.none')}
                            options={toSelectOptions(seriesOptions)}
                        />
                    </Field>

                    <Field label={t('establishments.billingCompany')} htmlFor="billing_company_id" error={errors.billing_company_id}>
                        <SearchableSelect
                            id="billing_company_id"
                            value={values.billing_company_id}
                            invalid={Boolean(errors.billing_company_id)}
                            onChange={(value) => onChange('billing_company_id', value)}
                            emptyLabel={t('common.none')}
                            options={toSelectOptions(companyOptions)}
                        />
                    </Field>

                    <Field label={t('establishments.responsibleUser')} htmlFor="responsible_user_id" error={errors.responsible_user_id}>
                        <SearchableSelect
                            id="responsible_user_id"
                            value={values.responsible_user_id}
                            invalid={Boolean(errors.responsible_user_id)}
                            onChange={(value) => onChange('responsible_user_id', value)}
                            emptyLabel={t('common.none')}
                            options={toSelectOptions(userOptions)}
                        />
                    </Field>
                    </div>
                </TabPanel>

                <TabPanel id="geo">
                    <div className="grid gap-5 sm:grid-cols-2">
                    <Field label={t('establishments.latitude')} htmlFor="latitude" error={errors.latitude}>
                        <Input
                            id="latitude"
                            type="number"
                            step="any"
                            value={values.latitude}
                            invalid={Boolean(errors.latitude)}
                            onChange={(event) => onChange('latitude', event.target.value)}
                        />
                    </Field>

                    <Field label={t('establishments.longitude')} htmlFor="longitude" error={errors.longitude}>
                        <Input
                            id="longitude"
                            type="number"
                            step="any"
                            value={values.longitude}
                            invalid={Boolean(errors.longitude)}
                            onChange={(event) => onChange('longitude', event.target.value)}
                        />
                    </Field>

                    <Field label={t('establishments.taxRate')} htmlFor="tax_rate" error={errors.tax_rate}>
                        <Input
                            id="tax_rate"
                            type="number"
                            step="any"
                            value={values.tax_rate}
                            invalid={Boolean(errors.tax_rate)}
                            onChange={(event) => onChange('tax_rate', event.target.value)}
                        />
                    </Field>

                    <div className="flex items-end">
                        <Toggle
                            name="tax_included"
                            checked={values.tax_included}
                            onCheckedChange={(checked) => onChange('tax_included', checked)}
                            checkedLabel={t('establishments.taxIncluded')}
                            uncheckedLabel={t('establishments.taxExcluded')}
                        />
                    </div>

                    <Field label={t('establishments.legacyErpId')} htmlFor="legacy_erp_id" error={errors.legacy_erp_id}>
                        <Input
                            id="legacy_erp_id"
                            type="number"
                            min={0}
                            value={values.legacy_erp_id}
                            invalid={Boolean(errors.legacy_erp_id)}
                            onChange={(event) => onChange('legacy_erp_id', event.target.value)}
                        />
                    </Field>

                    <Field label={t('establishments.integrationExternalId')} htmlFor="integration_external_id" error={errors.integration_external_id}>
                        <Input
                            id="integration_external_id"
                            value={values.integration_external_id}
                            invalid={Boolean(errors.integration_external_id)}
                            onChange={(event) => onChange('integration_external_id', event.target.value)}
                        />
                    </Field>
                    </div>
                </TabPanel>

                <TabPanel id="notes">
                    <div className="grid gap-5 sm:grid-cols-2">
                    <Field label={t('establishments.notes')} htmlFor="notes" error={errors.notes} className="sm:col-span-2">
                        <textarea
                            id="notes"
                            className={textareaClassName}
                            value={values.notes}
                            onChange={(event) => onChange('notes', event.target.value)}
                        />
                    </Field>

                    <Field label={t('establishments.internalNotes')} htmlFor="internal_notes" error={errors.internal_notes} className="sm:col-span-2">
                        <textarea
                            id="internal_notes"
                            className={textareaClassName}
                            value={values.internal_notes}
                            onChange={(event) => onChange('internal_notes', event.target.value)}
                        />
                    </Field>

                    <Toggle
                            name="notes_alert"
                        checked={values.notes_alert}
                        onCheckedChange={(checked) => onChange('notes_alert', checked)}
                        checkedLabel={t('establishments.notesAlert')}
                        uncheckedLabel={t('establishments.notesAlertOff')}
                       
                    />
                    <Toggle
                            name="internal_notes_alert"
                        checked={values.internal_notes_alert}
                        onCheckedChange={(checked) => onChange('internal_notes_alert', checked)}
                        checkedLabel={t('establishments.internalNotesAlert')}
                        uncheckedLabel={t('establishments.internalNotesAlertOff')}
                       
                    />
                    </div>
                </TabPanel>

                <TabPanel id="flags">
                    <div className="grid gap-5 sm:grid-cols-2">
                    <Toggle
                            name="is_active"
                        checked={values.is_active}
                        onCheckedChange={(checked) => onChange('is_active', checked)}
                        checkedLabel={t('common.active')}
                        uncheckedLabel={t('common.inactive')}
                    />
                    <Toggle
                            name="is_client_priority"
                        checked={values.is_client_priority}
                        onCheckedChange={(checked) => onChange('is_client_priority', checked)}
                        checkedLabel={t('establishments.isClientPriority')}
                        uncheckedLabel={t('establishments.isClientPriorityOff')}
                       
                    />
                    <Toggle
                            name="is_reviewed"
                        checked={values.is_reviewed}
                        onCheckedChange={(checked) => onChange('is_reviewed', checked)}
                        checkedLabel={t('establishments.isReviewed')}
                        uncheckedLabel={t('establishments.isReviewedOff')}
                    />
                    <Toggle
                            name="is_email_reviewed"
                        checked={values.is_email_reviewed}
                        onCheckedChange={(checked) => onChange('is_email_reviewed', checked)}
                        checkedLabel={t('establishments.isEmailReviewed')}
                        uncheckedLabel={t('establishments.isEmailReviewedOff')}
                    />
                    <Toggle
                            name="has_site_health_and_safety"
                        checked={values.has_site_health_and_safety}
                        onCheckedChange={(checked) => onChange('has_site_health_and_safety', checked)}
                        checkedLabel={t('establishments.hasSiteHealthAndSafety')}
                        uncheckedLabel={t('establishments.hasSiteHealthAndSafetyOff')}
                       
                    />
                    <Toggle
                            name="has_customer_health_and_safety"
                        checked={values.has_customer_health_and_safety}
                        onCheckedChange={(checked) => onChange('has_customer_health_and_safety', checked)}
                        checkedLabel={t('establishments.hasCustomerHealthAndSafety')}
                        uncheckedLabel={t('establishments.hasCustomerHealthAndSafetyOff')}
                       
                    />
                    <Toggle
                            name="is_quality_control_contactable"
                        checked={values.is_quality_control_contactable}
                        onCheckedChange={(checked) => onChange('is_quality_control_contactable', checked)}
                        checkedLabel={t('establishments.isQualityControlContactable')}
                        uncheckedLabel={t('establishments.isQualityControlContactableOff')}
                    />
                    <Toggle
                            name="has_parking"
                        checked={values.has_parking}
                        onCheckedChange={(checked) => onChange('has_parking', checked)}
                        checkedLabel={t('establishments.hasParking')}
                        uncheckedLabel={t('establishments.hasParkingOff')}
                    />
                    <Toggle
                            name="is_ulez_zone"
                        checked={values.is_ulez_zone}
                        onCheckedChange={(checked) => onChange('is_ulez_zone', checked)}
                        checkedLabel={t('establishments.isUlezZone')}
                        uncheckedLabel={t('establishments.isUlezZoneOff')}
                       
                    />
                    </div>
                </TabPanel>
            </Tabs>

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
