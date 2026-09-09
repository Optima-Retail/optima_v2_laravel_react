import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { MultiSelect } from '@/components/ui/MultiSelect';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { Select } from '@/components/ui/Select';
import { TabPanel, Tabs, type TabItem } from '@/components/ui/Tabs';
import { Toggle } from '@/components/ui/Toggle';
import { RichTextEditor } from '@/components/ui/RichTextEditor';
import type { RelationshipProfileValues } from '@/support/relationshipForm';
import type { UserOption } from '@/support/types/domain/common';
import type { RelationshipFormOptions } from '@/support/types/domain/company-relationship';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';

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
} & RelationshipProfileValues;

type RelationshipFormProps = {
    values: RelationshipFormValues;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    companyOptions: UserOption[];
    brandOptions?: UserOption[];
    formOptions?: RelationshipFormOptions;
    profileMode?: 'customer' | 'supplier';
    onChange: (key: string, value: string | boolean | string[]) => void;
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
const groupingBasisValues = ['', 'customer', 'establishment', 'work_order'] as const;

const emptyFormOptions: RelationshipFormOptions = {
    brandOptions: [],
    delegationOptions: [],
    languageOptions: [],
    seriesOptions: [],
    ratingTypeOptions: [],
    integrationOptions: [],
    userOptions: [],
    priorityOptions: [],
};

function toSelectOptions(options: UserOption[]) {
    return options.map((option) => ({
        value: String(option.id),
        label: option.label,
        color: option.color ?? null,
    }));
}

export function RelationshipForm({
    values,
    errors,
    processing,
    companyOptions,
    brandOptions = [],
    formOptions = emptyFormOptions,
    profileMode,
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
    const showBrandField = showBrand || profileMode === 'customer';
    const brandRequired = showBrandField && values.kind === 'customer';
    const visibleKinds = allowedKinds.filter((kind) => defaultKinds.includes(kind as (typeof defaultKinds)[number]));
    const creatingNew = allowCreateRelated && values.related_mode === 'new';
    const isCustomerProfile = profileMode === 'customer';
    const isSupplierProfile = profileMode === 'supplier';
    const resolvedBrandOptions = formOptions.brandOptions.length > 0 ? formOptions.brandOptions : brandOptions;
    const [activeTab, setActiveTab] = useState('general');

    const tabItems = useMemo(() => {
        const items: TabItem[] = [
            { id: 'general', label: t('relationships.tabs.general') },
            { id: 'catalog', label: t('relationships.tabs.catalog') },
            { id: 'notes', label: t('relationships.tabs.notes') },
            { id: 'metrics', label: t('relationships.tabs.metrics') },
        ];

        if (isCustomerProfile) {
            items.push(
                { id: 'owners', label: t('relationships.tabs.owners') },
                { id: 'operations', label: t('relationships.tabs.operations') },
            );
        }

        if (isSupplierProfile) {
            items.push({ id: 'supplier', label: t('relationships.tabs.supplier') });

            if (values.kind === 'technician') {
                items.push({ id: 'technician', label: t('relationships.tabs.technician') });
            }
        }

        return items;
    }, [isCustomerProfile, isSupplierProfile, t, values.kind]);

    useEffect(() => {
        if (activeTab === 'technician' && values.kind !== 'technician') {
            setActiveTab('general');
        }
    }, [activeTab, values.kind]);

    return (
        <FieldHelpScope table="company_relationships">
        <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <Tabs items={tabItems} value={activeTab} onValueChange={setActiveTab} defaultValue="general">
                <TabPanel id="general">
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
                            htmlFor="related_company_name" helpField="companies.name"
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

                        <Field label={t('companies.tradename')} htmlFor="related_company_tradename" helpField="companies.tradename" error={errors['related_company.tradename']}>
                            <Input
                                id="related_company_tradename"
                                value={values.related_company.tradename}
                                invalid={Boolean(errors['related_company.tradename'])}
                                onChange={(event) => onRelatedCompanyChange?.('tradename', event.target.value)}
                            />
                        </Field>

                        <Field label={t('companies.taxId')} htmlFor="related_company_tax_id" helpField="companies.tax_id" error={errors['related_company.tax_id']}>
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

                {showBrandField ? (
                    <Field label={t('relationships.brand')} htmlFor="brand_id" error={errors.brand_id}>
                        <SearchableSelect
                            id="brand_id"
                            value={values.brand_id}
                            invalid={Boolean(errors.brand_id)}
                            onChange={(value) => onChange('brand_id', value)}
                            emptyLabel={brandRequired ? t('common.select') : t('common.none')}
                            options={toSelectOptions(resolvedBrandOptions)}
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
                    <RichTextEditor
                        id="notes"
                        value={values.notes}
                        invalid={Boolean(errors.notes)}
                        onChange={(html) => onChange('notes', html)}
                    />
                </Field>
                    </div>
                </TabPanel>

                <TabPanel id="catalog">
                    <div className="grid gap-5 sm:grid-cols-2">
                    <Field label={t('relationships.delegation')} htmlFor="delegation_id" error={errors.delegation_id}>
                        <SearchableSelect
                            id="delegation_id"
                            value={values.delegation_id}
                            invalid={Boolean(errors.delegation_id)}
                            onChange={(value) => onChange('delegation_id', value)}
                            emptyLabel={t('common.none')}
                            options={toSelectOptions(formOptions.delegationOptions)}
                        />
                    </Field>

                    <Field label={t('relationships.billingLanguage')} htmlFor="billing_language_id" error={errors.billing_language_id}>
                        <SearchableSelect
                            id="billing_language_id"
                            value={values.billing_language_id}
                            invalid={Boolean(errors.billing_language_id)}
                            onChange={(value) => onChange('billing_language_id', value)}
                            emptyLabel={t('common.none')}
                            options={toSelectOptions(formOptions.languageOptions)}
                        />
                    </Field>

                    <Field label={t('relationships.series')} htmlFor="series_id" error={errors.series_id}>
                        <SearchableSelect
                            id="series_id"
                            value={values.series_id}
                            invalid={Boolean(errors.series_id)}
                            onChange={(value) => onChange('series_id', value)}
                            emptyLabel={t('common.none')}
                            options={toSelectOptions(formOptions.seriesOptions)}
                        />
                    </Field>

                    <Field label={t('relationships.ratingType')} htmlFor="rating_type_id" error={errors.rating_type_id}>
                        <SearchableSelect
                            id="rating_type_id"
                            value={values.rating_type_id}
                            invalid={Boolean(errors.rating_type_id)}
                            onChange={(value) => onChange('rating_type_id', value)}
                            emptyLabel={t('common.none')}
                            options={toSelectOptions(formOptions.ratingTypeOptions)}
                        />
                    </Field>

                    {isCustomerProfile ? (
                        <Field
                            label={t('relationships.priorities')}
                            htmlFor="priority_ids"
                            error={errors.priority_ids}
                            className="sm:col-span-2"
                        >
                            <MultiSelect
                                id="priority_ids"
                                value={values.priority_ids}
                                invalid={Boolean(errors.priority_ids)}
                                onChange={(value) => onChange('priority_ids', value)}
                                placeholder={t('relationships.prioritiesPlaceholder')}
                                options={toSelectOptions(formOptions.priorityOptions)}
                            />
                        </Field>
                    ) : null}

                    <Field label={t('relationships.integration')} htmlFor="integration_id" error={errors.integration_id}>
                        <SearchableSelect
                            id="integration_id"
                            value={values.integration_id}
                            invalid={Boolean(errors.integration_id)}
                            onChange={(value) => onChange('integration_id', value)}
                            emptyLabel={t('common.none')}
                            options={toSelectOptions(formOptions.integrationOptions)}
                        />
                    </Field>

                    <Field label={t('relationships.integrationExternalId')} htmlFor="integration_external_id" error={errors.integration_external_id}>
                        <Input
                            id="integration_external_id"
                            value={values.integration_external_id}
                            invalid={Boolean(errors.integration_external_id)}
                            onChange={(event) => onChange('integration_external_id', event.target.value)}
                        />
                    </Field>
                    </div>
                </TabPanel>

                {isCustomerProfile ? (
                    <TabPanel id="owners">
                        <div className="grid gap-5 sm:grid-cols-2">
                        <Field label={t('relationships.correctiveWorkOrderOwner')} htmlFor="corrective_work_order_owner_id" error={errors.corrective_work_order_owner_id}>
                            <SearchableSelect
                                id="corrective_work_order_owner_id"
                                value={values.corrective_work_order_owner_id}
                                invalid={Boolean(errors.corrective_work_order_owner_id)}
                                onChange={(value) => onChange('corrective_work_order_owner_id', value)}
                                emptyLabel={t('common.none')}
                                options={toSelectOptions(formOptions.userOptions)}
                            />
                        </Field>

                        <Field label={t('relationships.preventiveWorkOrderOwner')} htmlFor="preventive_work_order_owner_id" error={errors.preventive_work_order_owner_id}>
                            <SearchableSelect
                                id="preventive_work_order_owner_id"
                                value={values.preventive_work_order_owner_id}
                                invalid={Boolean(errors.preventive_work_order_owner_id)}
                                onChange={(value) => onChange('preventive_work_order_owner_id', value)}
                                emptyLabel={t('common.none')}
                                options={toSelectOptions(formOptions.userOptions)}
                            />
                        </Field>

                        <Field label={t('relationships.qualityOwner')} htmlFor="quality_owner_id" error={errors.quality_owner_id}>
                            <SearchableSelect
                                id="quality_owner_id"
                                value={values.quality_owner_id}
                                invalid={Boolean(errors.quality_owner_id)}
                                onChange={(value) => onChange('quality_owner_id', value)}
                                emptyLabel={t('common.none')}
                                options={toSelectOptions(formOptions.userOptions)}
                            />
                        </Field>

                        <Field label={t('relationships.accountOwner')} htmlFor="account_owner_id" error={errors.account_owner_id}>
                            <SearchableSelect
                                id="account_owner_id"
                                value={values.account_owner_id}
                                invalid={Boolean(errors.account_owner_id)}
                                onChange={(value) => onChange('account_owner_id', value)}
                                emptyLabel={t('common.none')}
                                options={toSelectOptions(formOptions.userOptions)}
                            />
                        </Field>

                        <Field label={t('relationships.commercialOwner')} htmlFor="commercial_owner_id" error={errors.commercial_owner_id}>
                            <SearchableSelect
                                id="commercial_owner_id"
                                value={values.commercial_owner_id}
                                invalid={Boolean(errors.commercial_owner_id)}
                                onChange={(value) => onChange('commercial_owner_id', value)}
                                emptyLabel={t('common.none')}
                                options={toSelectOptions(formOptions.userOptions)}
                            />
                        </Field>

                        <Field label={t('relationships.sourcedBy')} htmlFor="sourced_by_user_id" error={errors.sourced_by_user_id}>
                            <SearchableSelect
                                id="sourced_by_user_id"
                                value={values.sourced_by_user_id}
                                invalid={Boolean(errors.sourced_by_user_id)}
                                onChange={(value) => onChange('sourced_by_user_id', value)}
                                emptyLabel={t('common.none')}
                                options={toSelectOptions(formOptions.userOptions)}
                            />
                        </Field>
                        </div>
                    </TabPanel>
                ) : null}

                <TabPanel id="notes">
                    <div className="grid gap-5 sm:grid-cols-2">
                    <Field label={t('relationships.internalNotes')} htmlFor="internal_notes" error={errors.internal_notes} className="sm:col-span-2">
                        <RichTextEditor
                            id="internal_notes"
                            value={values.internal_notes}
                            invalid={Boolean(errors.internal_notes)}
                            onChange={(html) => onChange('internal_notes', html)}
                        />
                    </Field>

                    <Field label={t('relationships.onboardingNotes')} htmlFor="onboarding_notes" error={errors.onboarding_notes} className="sm:col-span-2">
                        <RichTextEditor
                            id="onboarding_notes"
                            value={values.onboarding_notes}
                            invalid={Boolean(errors.onboarding_notes)}
                            onChange={(html) => onChange('onboarding_notes', html)}
                        />
                    </Field>

                    <Field label={t('relationships.billingComments')} htmlFor="billing_comments" error={errors.billing_comments} className="sm:col-span-2">
                        <RichTextEditor
                            id="billing_comments"
                            value={values.billing_comments}
                            invalid={Boolean(errors.billing_comments)}
                            onChange={(html) => onChange('billing_comments', html)}
                        />
                    </Field>

                    <Field label={t('relationships.ratesNotes')} htmlFor="rates_notes" error={errors.rates_notes} className="sm:col-span-2">
                        <RichTextEditor
                            id="rates_notes"
                            value={values.rates_notes}
                            invalid={Boolean(errors.rates_notes)}
                            onChange={(html) => onChange('rates_notes', html)}
                        />
                    </Field>

                    <Field label={t('relationships.archetype')} htmlFor="archetype" error={errors.archetype}>
                        <Input
                            id="archetype"
                            value={values.archetype}
                            invalid={Boolean(errors.archetype)}
                            onChange={(event) => onChange('archetype', event.target.value)}
                        />
                    </Field>

                    <div className="sm:col-span-2 grid gap-4 sm:grid-cols-2">
                        <Toggle
                            name="notes_alert"
                            checked={values.notes_alert}
                            onCheckedChange={(checked) => onChange('notes_alert', checked)}
                            checkedLabel={t('relationships.notesAlert')}
                            uncheckedLabel={t('relationships.notesAlertOff')}
                        />
                        <Toggle
                            name="internal_notes_alert"
                            checked={values.internal_notes_alert}
                            onCheckedChange={(checked) => onChange('internal_notes_alert', checked)}
                            checkedLabel={t('relationships.internalNotesAlert')}
                            uncheckedLabel={t('relationships.internalNotesAlertOff')}
                        />
                    </div>
                    </div>
                </TabPanel>

                <TabPanel id="metrics">
                    <div className="grid gap-5 sm:grid-cols-2">
                    <Field label={t('relationships.taxRate')} htmlFor="tax_rate" error={errors.tax_rate}>
                        <Input
                            id="tax_rate"
                            type="number"
                            step="any"
                            value={values.tax_rate}
                            invalid={Boolean(errors.tax_rate)}
                            onChange={(event) => onChange('tax_rate', event.target.value)}
                        />
                    </Field>

                    <Field label={t('relationships.quoteCloseDays')} htmlFor="quote_close_days" error={errors.quote_close_days}>
                        <Input
                            id="quote_close_days"
                            type="number"
                            min={0}
                            value={values.quote_close_days}
                            invalid={Boolean(errors.quote_close_days)}
                            onChange={(event) => onChange('quote_close_days', event.target.value)}
                        />
                    </Field>

                    <Field label={t('relationships.recurringMeetingFrequency')} htmlFor="recurring_meeting_frequency" error={errors.recurring_meeting_frequency}>
                        <Input
                            id="recurring_meeting_frequency"
                            type="number"
                            min={0}
                            value={values.recurring_meeting_frequency}
                            invalid={Boolean(errors.recurring_meeting_frequency)}
                            onChange={(event) => onChange('recurring_meeting_frequency', event.target.value)}
                        />
                    </Field>

                    <Field label={t('relationships.salesFeedbackMeetingFrequency')} htmlFor="sales_feedback_meeting_frequency" error={errors.sales_feedback_meeting_frequency}>
                        <Input
                            id="sales_feedback_meeting_frequency"
                            type="number"
                            min={0}
                            value={values.sales_feedback_meeting_frequency}
                            invalid={Boolean(errors.sales_feedback_meeting_frequency)}
                            onChange={(event) => onChange('sales_feedback_meeting_frequency', event.target.value)}
                        />
                    </Field>

                    <Field label={t('relationships.optimaScore')} htmlFor="optima_score" error={errors.optima_score}>
                        <Input
                            id="optima_score"
                            type="number"
                            step="any"
                            value={values.optima_score}
                            invalid={Boolean(errors.optima_score)}
                            onChange={(event) => onChange('optima_score', event.target.value)}
                        />
                    </Field>

                    <Field label={t('relationships.customerScore')} htmlFor="customer_score" error={errors.customer_score}>
                        <Input
                            id="customer_score"
                            type="number"
                            step="any"
                            value={values.customer_score}
                            invalid={Boolean(errors.customer_score)}
                            onChange={(event) => onChange('customer_score', event.target.value)}
                        />
                    </Field>

                    <Field label={t('relationships.averageScore')} htmlFor="average_score" error={errors.average_score}>
                        <Input
                            id="average_score"
                            type="number"
                            step="any"
                            value={values.average_score}
                            invalid={Boolean(errors.average_score)}
                            onChange={(event) => onChange('average_score', event.target.value)}
                        />
                    </Field>

                    <Field label={t('relationships.optimaScoreCount')} htmlFor="optima_score_count" error={errors.optima_score_count}>
                        <Input
                            id="optima_score_count"
                            type="number"
                            min={0}
                            value={values.optima_score_count}
                            invalid={Boolean(errors.optima_score_count)}
                            onChange={(event) => onChange('optima_score_count', event.target.value)}
                        />
                    </Field>

                    <Field label={t('relationships.customerScoreCount')} htmlFor="customer_score_count" error={errors.customer_score_count}>
                        <Input
                            id="customer_score_count"
                            type="number"
                            min={0}
                            value={values.customer_score_count}
                            invalid={Boolean(errors.customer_score_count)}
                            onChange={(event) => onChange('customer_score_count', event.target.value)}
                        />
                    </Field>
                    </div>
                </TabPanel>

                {isCustomerProfile ? (
                    <TabPanel id="operations">
                        <div className="grid gap-5 sm:grid-cols-2">
                        <Toggle
                            name="is_reviewed"
                            checked={values.is_reviewed}
                            onCheckedChange={(checked) => onChange('is_reviewed', checked)}
                            checkedLabel={t('relationships.isReviewed')}
                            uncheckedLabel={t('relationships.isReviewedOff')}
                        />
                        <Toggle
                            name="is_email_reviewed"
                            checked={values.is_email_reviewed}
                            onCheckedChange={(checked) => onChange('is_email_reviewed', checked)}
                            checkedLabel={t('relationships.isEmailReviewed')}
                            uncheckedLabel={t('relationships.isEmailReviewedOff')}
                        />
                        <Toggle
                            name="is_invoicing_reviewed"
                            checked={values.is_invoicing_reviewed}
                            onCheckedChange={(checked) => onChange('is_invoicing_reviewed', checked)}
                            checkedLabel={t('relationships.isInvoicingReviewed')}
                            uncheckedLabel={t('relationships.isInvoicingReviewedOff')}
                        />

                        <Field label={t('relationships.invoicingReviewedAt')} htmlFor="invoicing_reviewed_at" error={errors.invoicing_reviewed_at}>
                            <Input
                                id="invoicing_reviewed_at"
                                type="date"
                                value={values.invoicing_reviewed_at}
                                invalid={Boolean(errors.invoicing_reviewed_at)}
                                onChange={(event) => onChange('invoicing_reviewed_at', event.target.value)}
                            />
                        </Field>

                        <Toggle
                            name="group_zero_cost_work_orders"
                            checked={values.group_zero_cost_work_orders}
                            onCheckedChange={(checked) => onChange('group_zero_cost_work_orders', checked)}
                            checkedLabel={t('relationships.groupZeroCostWorkOrders')}
                            uncheckedLabel={t('relationships.groupZeroCostWorkOrdersOff')}
                           
                        />
                        <Toggle
                            name="load_materials_on_corrective"
                            checked={values.load_materials_on_corrective}
                            onCheckedChange={(checked) => onChange('load_materials_on_corrective', checked)}
                            checkedLabel={t('relationships.loadMaterialsOnCorrective')}
                            uncheckedLabel={t('relationships.loadMaterialsOnCorrectiveOff')}
                           
                        />
                        <Toggle
                            name="group_preventive_and_corrective"
                            checked={values.group_preventive_and_corrective}
                            onCheckedChange={(checked) => onChange('group_preventive_and_corrective', checked)}
                            checkedLabel={t('relationships.groupPreventiveAndCorrective')}
                            uncheckedLabel={t('relationships.groupPreventiveAndCorrectiveOff')}
                           
                        />

                        <Field label={t('relationships.groupPreventivesBy')} htmlFor="group_preventives_by" error={errors.group_preventives_by}>
                            <Select
                                id="group_preventives_by"
                                value={values.group_preventives_by}
                                invalid={Boolean(errors.group_preventives_by)}
                                onChange={(event) => onChange('group_preventives_by', event.target.value)}
                            >
                                {groupingBasisValues.map((basis) => (
                                    <option key={basis || 'none'} value={basis}>
                                        {basis ? t(`relationships.groupingBasis.${basis}`) : t('common.none')}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <Field label={t('relationships.groupCorrectivesBy')} htmlFor="group_correctives_by" error={errors.group_correctives_by}>
                            <Select
                                id="group_correctives_by"
                                value={values.group_correctives_by}
                                invalid={Boolean(errors.group_correctives_by)}
                                onChange={(event) => onChange('group_correctives_by', event.target.value)}
                            >
                                {groupingBasisValues.map((basis) => (
                                    <option key={basis || 'none'} value={basis}>
                                        {basis ? t(`relationships.groupingBasis.${basis}`) : t('common.none')}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <Toggle
                            name="invoice_at_month_end"
                            checked={values.invoice_at_month_end}
                            onCheckedChange={(checked) => onChange('invoice_at_month_end', checked)}
                            checkedLabel={t('relationships.invoiceAtMonthEnd')}
                            uncheckedLabel={t('relationships.invoiceAtMonthEndOff')}
                           
                        />
                        <Toggle
                            name="requires_purchase_order"
                            checked={values.requires_purchase_order}
                            onCheckedChange={(checked) => onChange('requires_purchase_order', checked)}
                            checkedLabel={t('relationships.requiresPurchaseOrder')}
                            uncheckedLabel={t('relationships.requiresPurchaseOrderOff')}
                           
                        />
                        <Toggle
                            name="requires_requester"
                            checked={values.requires_requester}
                            onCheckedChange={(checked) => onChange('requires_requester', checked)}
                            checkedLabel={t('relationships.requiresRequester')}
                            uncheckedLabel={t('relationships.requiresRequesterOff')}
                           
                        />
                        <Toggle
                            name="is_franchise"
                            checked={values.is_franchise}
                            onCheckedChange={(checked) => onChange('is_franchise', checked)}
                            checkedLabel={t('relationships.isFranchise')}
                            uncheckedLabel={t('relationships.isFranchiseOff')}
                           
                        />
                        <Toggle
                            name="requires_justification"
                            checked={values.requires_justification}
                            onCheckedChange={(checked) => onChange('requires_justification', checked)}
                            checkedLabel={t('relationships.requiresJustification')}
                            uncheckedLabel={t('relationships.requiresJustificationOff')}
                           
                        />
                        <Toggle
                            name="auto_send_invoices"
                            checked={values.auto_send_invoices}
                            onCheckedChange={(checked) => onChange('auto_send_invoices', checked)}
                            checkedLabel={t('relationships.autoSendInvoices')}
                            uncheckedLabel={t('relationships.autoSendInvoicesOff')}
                           
                        />
                        <Toggle
                            name="send_invoices_individually"
                            checked={values.send_invoices_individually}
                            onCheckedChange={(checked) => onChange('send_invoices_individually', checked)}
                            checkedLabel={t('relationships.sendInvoicesIndividually')}
                            uncheckedLabel={t('relationships.sendInvoicesIndividuallyOff')}
                           
                        />
                        <Toggle
                            name="send_debt_reminders"
                            checked={values.send_debt_reminders}
                            onCheckedChange={(checked) => onChange('send_debt_reminders', checked)}
                            checkedLabel={t('relationships.sendDebtReminders')}
                            uncheckedLabel={t('relationships.sendDebtRemindersOff')}
                           
                        />
                        <Toggle
                            name="is_quality_control_contactable"
                            checked={values.is_quality_control_contactable}
                            onCheckedChange={(checked) => onChange('is_quality_control_contactable', checked)}
                            checkedLabel={t('relationships.isQualityControlContactable')}
                            uncheckedLabel={t('relationships.isQualityControlContactableOff')}
                           
                        />
                        <Toggle
                            name="requires_client_informed_check"
                            checked={values.requires_client_informed_check}
                            onCheckedChange={(checked) => onChange('requires_client_informed_check', checked)}
                            checkedLabel={t('relationships.requiresClientInformedCheck')}
                            uncheckedLabel={t('relationships.requiresClientInformedCheckOff')}
                           
                        />
                        <Toggle
                            name="requires_intervention_scheduled_check"
                            checked={values.requires_intervention_scheduled_check}
                            onCheckedChange={(checked) => onChange('requires_intervention_scheduled_check', checked)}
                            checkedLabel={t('relationships.requiresInterventionScheduledCheck')}
                            uncheckedLabel={t('relationships.requiresInterventionScheduledCheckOff')}
                           
                        />
                        <Toggle
                            name="requires_budget_approval_limit"
                            checked={values.requires_budget_approval_limit}
                            onCheckedChange={(checked) => onChange('requires_budget_approval_limit', checked)}
                            checkedLabel={t('relationships.requiresBudgetApprovalLimit')}
                            uncheckedLabel={t('relationships.requiresBudgetApprovalLimitOff')}
                           
                        />
                        <Toggle
                            name="is_intercompany"
                            checked={values.is_intercompany}
                            onCheckedChange={(checked) => onChange('is_intercompany', checked)}
                            checkedLabel={t('relationships.isIntercompany')}
                            uncheckedLabel={t('relationships.isIntercompanyOff')}
                           
                        />
                        </div>
                    </TabPanel>
                ) : null}

                {isSupplierProfile ? (
                    <TabPanel id="supplier">
                        <div className="grid gap-5 sm:grid-cols-2">
                        <Toggle
                            name="is_creditor"
                            checked={values.is_creditor}
                            onCheckedChange={(checked) => onChange('is_creditor', checked)}
                            checkedLabel={t('relationships.isCreditor')}
                            uncheckedLabel={t('relationships.isCreditorOff')}
                           
                        />
                        <Toggle
                            name="is_vip"
                            checked={values.is_vip}
                            onCheckedChange={(checked) => onChange('is_vip', checked)}
                            checkedLabel={t('relationships.isVip')}
                            uncheckedLabel={t('relationships.isVipOff')}
                        />
                        <Toggle
                            name="has_garnishment"
                            checked={values.has_garnishment}
                            onCheckedChange={(checked) => onChange('has_garnishment', checked)}
                            checkedLabel={t('relationships.hasGarnishment')}
                            uncheckedLabel={t('relationships.hasGarnishmentOff')}
                           
                        />
                        <Toggle
                            name="whatsapp_messaging_authorized"
                            checked={values.whatsapp_messaging_authorized}
                            onCheckedChange={(checked) => onChange('whatsapp_messaging_authorized', checked)}
                            checkedLabel={t('relationships.whatsappMessagingAuthorized')}
                            uncheckedLabel={t('relationships.whatsappMessagingAuthorizedOff')}
                           
                        />
                        <Toggle
                            name="has_health_and_safety"
                            checked={values.has_health_and_safety}
                            onCheckedChange={(checked) => onChange('has_health_and_safety', checked)}
                            checkedLabel={t('relationships.hasHealthAndSafety')}
                            uncheckedLabel={t('relationships.hasHealthAndSafetyOff')}
                           
                        />
                        </div>
                    </TabPanel>
                ) : null}

                {isSupplierProfile && values.kind === 'technician' ? (
                    <TabPanel id="technician">
                        <div className="grid gap-5 sm:grid-cols-2">
                        <Toggle
                            name="is_field_technician"
                            checked={values.is_field_technician}
                            onCheckedChange={(checked) => onChange('is_field_technician', checked)}
                            checkedLabel={t('relationships.isFieldTechnician')}
                            uncheckedLabel={t('relationships.isFieldTechnicianOff')}
                           
                        />
                        <Toggle
                            name="is_available_24h"
                            checked={values.is_available_24h}
                            onCheckedChange={(checked) => onChange('is_available_24h', checked)}
                            checkedLabel={t('relationships.isAvailable24h')}
                            uncheckedLabel={t('relationships.isAvailable24hOff')}
                           
                        />

                        <Field label={t('relationships.dayStartAt')} htmlFor="day_start_at" error={errors.day_start_at}>
                            <Input
                                id="day_start_at"
                                type="time"
                                value={values.day_start_at}
                                invalid={Boolean(errors.day_start_at)}
                                onChange={(event) => onChange('day_start_at', event.target.value)}
                            />
                        </Field>

                        <Field label={t('relationships.dayEndAt')} htmlFor="day_end_at" error={errors.day_end_at}>
                            <Input
                                id="day_end_at"
                                type="time"
                                value={values.day_end_at}
                                invalid={Boolean(errors.day_end_at)}
                                onChange={(event) => onChange('day_end_at', event.target.value)}
                            />
                        </Field>

                        <Field label={t('relationships.registeredAt')} htmlFor="registered_at" error={errors.registered_at}>
                            <Input
                                id="registered_at"
                                type="date"
                                value={values.registered_at}
                                invalid={Boolean(errors.registered_at)}
                                onChange={(event) => onChange('registered_at', event.target.value)}
                            />
                        </Field>

                        <Field label={t('relationships.legacyStatusId')} htmlFor="legacy_status_id" error={errors.legacy_status_id}>
                            <Input
                                id="legacy_status_id"
                                type="number"
                                min={0}
                                value={values.legacy_status_id}
                                invalid={Boolean(errors.legacy_status_id)}
                                onChange={(event) => onChange('legacy_status_id', event.target.value)}
                            />
                        </Field>
                        </div>
                    </TabPanel>
                ) : null}
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
