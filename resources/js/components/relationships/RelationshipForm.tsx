import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { Select } from '@/components/ui/Select';
import type { UserOption } from '@/support/types/domain';

export type RelationshipFormValues = {
    related_mode: 'existing' | 'new';
    related_company_id: string;
    related_company: {
        name: string;
        tradename: string;
        tax_id: string;
        email: string;
        phone: string;
    };
    kind: string;
    status: string;
    classification: string;
    owner_reference: string;
    related_reference: string;
    brand_id: string;
    external_code: string;
    notes: string;
    starts_at: string;
    ends_at: string;
};

type RelationshipFormProps = {
    values: RelationshipFormValues;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    companyOptions: UserOption[];
    brandOptions?: UserOption[];
    onChange: (key: string, value: string) => void;
    onRelatedCompanyChange?: (key: keyof RelationshipFormValues['related_company'], value: string) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
    allowedKinds?: string[];
    kindLocked?: boolean;
    kindLabelsNamespace?: string;
    allowCreateRelated?: boolean;
    showBrand?: boolean;
};

const defaultKinds = ['customer', 'supplier', 'technician', 'partner'] as const;
const statuses = ['prospect', 'active', 'blocked', 'inactive', 'archived'] as const;
const classifications = ['commercial', 'intercompany', 'public_administration', 'bank', 'other'] as const;

export function RelationshipForm({
    values,
    errors,
    processing,
    companyOptions,
    brandOptions = [],
    onChange,
    onRelatedCompanyChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
    allowedKinds = [...defaultKinds],
    kindLocked = false,
    kindLabelsNamespace = 'relationships',
    allowCreateRelated = false,
    showBrand = false,
}: RelationshipFormProps) {
    const { t } = useTranslation();
    const brandRequired = showBrand && values.kind === 'customer';
    const visibleKinds = allowedKinds.filter((kind) => defaultKinds.includes(kind as (typeof defaultKinds)[number]));
    const creatingNew = allowCreateRelated && values.related_mode === 'new';

    return (
        <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <div className="grid gap-5 sm:grid-cols-2">
                {allowCreateRelated ? (
                    <Field
                        label={t('relationships.relatedMode')}
                        htmlFor="related_mode"
                        error={errors.related_mode}
                        className="sm:col-span-2"
                        required
                    >
                        <Select
                            id="related_mode"
                            value={values.related_mode}
                            invalid={Boolean(errors.related_mode)}
                            onChange={(event) => onChange('related_mode', event.target.value)}
                        >
                            <option value="existing">{t('relationships.relatedModeExisting')}</option>
                            <option value="new">{t('relationships.relatedModeNew')}</option>
                        </Select>
                    </Field>
                ) : null}

                {creatingNew ? (
                    <>
                        <Field
                            label={t('common.name')}
                            htmlFor="related_company_name"
                            error={errors['related_company.name']}
                            className="sm:col-span-2"
                            required
                        >
                            <Input
                                id="related_company_name"
                                value={values.related_company.name}
                                invalid={Boolean(errors['related_company.name'])}
                                onChange={(event) => onRelatedCompanyChange?.('name', event.target.value)}
                            />
                        </Field>

                        <Field label={t('companies.tradename')} htmlFor="related_company_tradename" error={errors['related_company.tradename']}>
                            <Input
                                id="related_company_tradename"
                                value={values.related_company.tradename}
                                invalid={Boolean(errors['related_company.tradename'])}
                                onChange={(event) => onRelatedCompanyChange?.('tradename', event.target.value)}
                            />
                        </Field>

                        <Field label={t('companies.taxId')} htmlFor="related_company_tax_id" error={errors['related_company.tax_id']}>
                            <Input
                                id="related_company_tax_id"
                                value={values.related_company.tax_id}
                                invalid={Boolean(errors['related_company.tax_id'])}
                                onChange={(event) => onRelatedCompanyChange?.('tax_id', event.target.value)}
                            />
                        </Field>

                        <Field label={t('common.email')} htmlFor="related_company_email" error={errors['related_company.email']}>
                            <Input
                                id="related_company_email"
                                type="email"
                                value={values.related_company.email}
                                invalid={Boolean(errors['related_company.email'])}
                                onChange={(event) => onRelatedCompanyChange?.('email', event.target.value)}
                            />
                        </Field>

                        <Field label={t('companies.phone')} htmlFor="related_company_phone" error={errors['related_company.phone']}>
                            <Input
                                id="related_company_phone"
                                value={values.related_company.phone}
                                invalid={Boolean(errors['related_company.phone'])}
                                onChange={(event) => onRelatedCompanyChange?.('phone', event.target.value)}
                            />
                        </Field>
                    </>
                ) : (
                    <Field
                        label={t('relationships.relatedCompany')}
                        htmlFor="related_company_id"
                        error={errors.related_company_id}
                        className="sm:col-span-2"
                        required
                    >
                        <SearchableSelect
                            id="related_company_id"
                            value={values.related_company_id}
                            invalid={Boolean(errors.related_company_id)}
                            onChange={(value) => onChange('related_company_id', value)}
                            options={companyOptions.map((option) => ({
                                value: String(option.id),
                                label: option.label,
                            }))}
                        />
                    </Field>
                )}

                {!kindLocked ? (
                    <Field label={t('relationships.kind')} htmlFor="kind" error={errors.kind} required>
                        <Select
                            id="kind"
                            value={values.kind}
                            invalid={Boolean(errors.kind)}
                            onChange={(event) => onChange('kind', event.target.value)}
                        >
                            {visibleKinds.map((kind) => (
                                <option key={kind} value={kind}>
                                    {t(`${kindLabelsNamespace}.kinds.${kind}`)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                ) : null}

                <Field label={t('common.status')} htmlFor="status" error={errors.status} required>
                    <Select
                        id="status"
                        value={values.status}
                        invalid={Boolean(errors.status)}
                        onChange={(event) => onChange('status', event.target.value)}
                    >
                        {statuses.map((status) => (
                            <option key={status} value={status}>
                                {t(`relationships.statuses.${status}`)}
                            </option>
                        ))}
                    </Select>
                </Field>

                <Field label={t('relationships.classification')} htmlFor="classification" error={errors.classification} required>
                    <Select
                        id="classification"
                        value={values.classification}
                        invalid={Boolean(errors.classification)}
                        onChange={(event) => onChange('classification', event.target.value)}
                    >
                        {classifications.map((classification) => (
                            <option key={classification} value={classification}>
                                {t(`relationships.classifications.${classification}`)}
                            </option>
                        ))}
                    </Select>
                </Field>

                {showBrand ? (
                    <Field label={t('relationships.brand')} htmlFor="brand_id" error={errors.brand_id}>
                        <SearchableSelect
                            id="brand_id"
                            value={values.brand_id}
                            invalid={Boolean(errors.brand_id)}
                            onChange={(value) => onChange('brand_id', value)}
                            emptyLabel={brandRequired ? t('common.select') : t('common.none')}
                            options={brandOptions.map((option) => ({
                                value: String(option.id),
                                label: option.label,
                            }))}
                        />
                    </Field>
                ) : null}

                <Field label={t('relationships.ownerReference')} htmlFor="owner_reference" error={errors.owner_reference}>
                    <Input
                        id="owner_reference"
                        value={values.owner_reference}
                        invalid={Boolean(errors.owner_reference)}
                        onChange={(event) => onChange('owner_reference', event.target.value)}
                    />
                </Field>

                <Field label={t('relationships.relatedReference')} htmlFor="related_reference" error={errors.related_reference}>
                    <Input
                        id="related_reference"
                        value={values.related_reference}
                        invalid={Boolean(errors.related_reference)}
                        onChange={(event) => onChange('related_reference', event.target.value)}
                    />
                </Field>

                <Field label={t('relationships.externalCode')} htmlFor="external_code" error={errors.external_code}>
                    <Input
                        id="external_code"
                        value={values.external_code}
                        invalid={Boolean(errors.external_code)}
                        onChange={(event) => onChange('external_code', event.target.value)}
                    />
                </Field>

                <Field label={t('relationships.startsAt')} htmlFor="starts_at" error={errors.starts_at}>
                    <Input
                        id="starts_at"
                        type="date"
                        value={values.starts_at}
                        invalid={Boolean(errors.starts_at)}
                        onChange={(event) => onChange('starts_at', event.target.value)}
                    />
                </Field>

                <Field label={t('relationships.endsAt')} htmlFor="ends_at" error={errors.ends_at}>
                    <Input
                        id="ends_at"
                        type="date"
                        value={values.ends_at}
                        invalid={Boolean(errors.ends_at)}
                        onChange={(event) => onChange('ends_at', event.target.value)}
                    />
                </Field>

                <Field label={t('relationships.notes')} htmlFor="notes" error={errors.notes} className="sm:col-span-2">
                    <Input
                        id="notes"
                        value={values.notes}
                        invalid={Boolean(errors.notes)}
                        onChange={(event) => onChange('notes', event.target.value)}
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
