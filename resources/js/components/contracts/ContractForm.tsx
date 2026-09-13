import { useMemo, type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import {
    ContractSchedulePanel,
    emptyAggregation,
    emptyIteration,
} from '@/components/contracts/ContractSchedulePanel';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { MultiSelect } from '@/components/ui/MultiSelect';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import type { UserOption } from '@/support/types/domain/common';
import type {
    ContractFormData,
    ContractInvoicingAggregationFormValues,
    ContractIterationFormValues,
} from '@/support/types/domain/contract';
import type { EstablishmentOption } from '@/support/types/domain/establishment';

export type ContractFormValues = {
    code: string;
    description: string;
    work_order_subject: string;
    company_id: string;
    responsible_user_id: string;
    contract_status_id: string;
    language_id: string;
    signed_at: string;
    canceled_at: string;
    establishment_ids: string[];
    iterations: ContractIterationFormValues[];
    invoicing_aggregations: ContractInvoicingAggregationFormValues[];
};

type ContractFormProps = {
    values: ContractFormValues;
    errors: Record<string, string | undefined>;
    processing: boolean;
    codeDisabled?: boolean;
    companyOptions: UserOption[];
    contractStatusOptions: UserOption[];
    languageOptions: UserOption[];
    userOptions: UserOption[];
    establishmentOptions: EstablishmentOption[];
    workOrderTypeOptions: UserOption[];
    formTemplateOptions: UserOption[];
    onChange: (key: keyof ContractFormValues, value: ContractFormValues[keyof ContractFormValues]) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

function toSelectOptions(options: UserOption[]) {
    return options.map((option) => ({
        value: String(option.id),
        label: option.label,
        color: option.color ?? null,
    }));
}

function mapIterationFromServer(
    row: ContractFormData['iterations'][number],
): ContractIterationFormValues {
    return {
        id: row.id,
        temp_key: row.temp_key,
        subject: row.subject ?? '',
        work_order_type_id: row.work_order_type_id ? String(row.work_order_type_id) : '',
        starts_on: row.starts_on ?? '',
        ends_on: row.ends_on ?? '',
        periodicity: (row.periodicity as ContractIterationFormValues['periodicity']) || 'monthly',
        periodicity_kind: (row.periodicity_kind as ContractIterationFormValues['periodicity_kind']) || 'basic',
        interval: row.interval != null ? String(row.interval) : '',
        weekdays: row.weekdays.map(String),
        month_days: row.month_days.map(String),
        months: row.months.map(String),
        cost_amount: row.cost_amount != null ? String(row.cost_amount) : '0',
        establishment_ids: row.establishment_ids.map(String),
        form_template_id: row.form_template_id ? String(row.form_template_id) : '',
        invoicing_aggregation_id: row.invoicing_aggregation_id ? String(row.invoicing_aggregation_id) : '',
        invoicing_aggregation_temp_key: row.invoicing_aggregation_temp_key ?? '',
    };
}

function mapAggregationFromServer(
    row: ContractFormData['invoicing_aggregations'][number],
): ContractInvoicingAggregationFormValues {
    return {
        id: row.id,
        temp_key: row.temp_key,
        subject: row.subject ?? '',
        billing_frequency:
            (row.billing_frequency as ContractInvoicingAggregationFormValues['billing_frequency']) || 'monthly',
        billing_day: row.billing_day != null ? String(row.billing_day) : '',
        billing_cycle_start: row.billing_cycle_start ?? '',
        per_establishment: Boolean(row.per_establishment),
    };
}

export function defaultContractFormValues(
    overrides: Partial<ContractFormValues> = {},
): ContractFormValues {
    return {
        code: '',
        description: '',
        work_order_subject: '',
        company_id: '',
        responsible_user_id: '',
        contract_status_id: '',
        language_id: '',
        signed_at: '',
        canceled_at: '',
        establishment_ids: [],
        iterations: [],
        invoicing_aggregations: [],
        ...overrides,
    };
}

export function contractFormValuesFromData(contract: ContractFormData): ContractFormValues {
    return defaultContractFormValues({
        code: contract.code ?? '',
        description: contract.description ?? '',
        work_order_subject: contract.work_order_subject ?? '',
        company_id: contract.company_id ? String(contract.company_id) : '',
        responsible_user_id: contract.responsible_user_id ? String(contract.responsible_user_id) : '',
        contract_status_id: contract.contract_status_id ? String(contract.contract_status_id) : '',
        language_id: contract.language_id ? String(contract.language_id) : '',
        signed_at: contract.signed_at ?? '',
        canceled_at: contract.canceled_at ?? '',
        establishment_ids: contract.establishment_ids.map(String),
        iterations: (contract.iterations ?? []).map(mapIterationFromServer),
        invoicing_aggregations: (contract.invoicing_aggregations ?? []).map(mapAggregationFromServer),
    });
}

export function ContractForm({
    values,
    errors,
    processing,
    codeDisabled = false,
    companyOptions,
    contractStatusOptions,
    languageOptions,
    userOptions,
    establishmentOptions,
    workOrderTypeOptions,
    formTemplateOptions,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: ContractFormProps) {
    const { t } = useTranslation();

    const filteredEstablishments = useMemo(() => {
        if (!values.company_id) {
            return [];
        }

        return establishmentOptions
            .filter((option) => String(option.company_id) === values.company_id)
            .map((option) => ({
                value: String(option.id),
                label: option.label,
            }));
    }, [establishmentOptions, values.company_id]);

    return (
        <FieldHelpScope table="contracts">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <div className="grid gap-5 sm:grid-cols-2">
                    <Field label={t('contracts.description')} htmlFor="description" error={errors.description} className="sm:col-span-2" required>
                        <Input
                            id="description"
                            value={values.description}
                            invalid={Boolean(errors.description)}
                            onChange={(event) => onChange('description', event.target.value)}
                        />
                    </Field>

                    <Field label={t('common.code')} htmlFor="code" error={errors.code}>
                        <Input
                            id="code"
                            value={values.code}
                            invalid={Boolean(errors.code)}
                            disabled={codeDisabled}
                            readOnly={codeDisabled}
                            onChange={(event) => onChange('code', event.target.value)}
                        />
                        {codeDisabled ? (
                            <p className="mt-1 text-xs text-muted">{t('contracts.codeAutomaticHint')}</p>
                        ) : null}
                    </Field>

                    <Field label={t('contracts.client')} htmlFor="company_id" error={errors.company_id} required>
                        <SearchableSelect
                            id="company_id"
                            value={values.company_id}
                            options={toSelectOptions(companyOptions)}
                            invalid={Boolean(errors.company_id)}
                            onChange={(value) => onChange('company_id', value)}
                        />
                    </Field>

                    <Field label={t('contracts.status')} htmlFor="contract_status_id" error={errors.contract_status_id} required>
                        <SearchableSelect
                            id="contract_status_id"
                            value={values.contract_status_id}
                            options={toSelectOptions(contractStatusOptions)}
                            invalid={Boolean(errors.contract_status_id)}
                            onChange={(value) => onChange('contract_status_id', value)}
                        />
                    </Field>

                    <Field label={t('contracts.responsibleUser')} htmlFor="responsible_user_id" error={errors.responsible_user_id} required>
                        <SearchableSelect
                            id="responsible_user_id"
                            value={values.responsible_user_id}
                            options={toSelectOptions(userOptions)}
                            invalid={Boolean(errors.responsible_user_id)}
                            onChange={(value) => onChange('responsible_user_id', value)}
                        />
                    </Field>

                    <Field label={t('contracts.language')} htmlFor="language_id" error={errors.language_id}>
                        <SearchableSelect
                            id="language_id"
                            value={values.language_id}
                            options={toSelectOptions(languageOptions)}
                            invalid={Boolean(errors.language_id)}
                            onChange={(value) => onChange('language_id', value)}
                        />
                    </Field>

                    <Field label={t('contracts.signedAt')} htmlFor="signed_at" error={errors.signed_at}>
                        <Input
                            id="signed_at"
                            type="date"
                            value={values.signed_at}
                            invalid={Boolean(errors.signed_at)}
                            onChange={(event) => onChange('signed_at', event.target.value)}
                        />
                    </Field>

                    <Field label={t('contracts.canceledAt')} htmlFor="canceled_at" error={errors.canceled_at}>
                        <Input
                            id="canceled_at"
                            type="date"
                            value={values.canceled_at}
                            invalid={Boolean(errors.canceled_at)}
                            onChange={(event) => onChange('canceled_at', event.target.value)}
                        />
                    </Field>

                    <Field
                        label={t('contracts.workOrderSubject')}
                        htmlFor="work_order_subject"
                        error={errors.work_order_subject}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="work_order_subject"
                            value={values.work_order_subject}
                            invalid={Boolean(errors.work_order_subject)}
                            onChange={(event) => onChange('work_order_subject', event.target.value)}
                        />
                    </Field>

                    <Field
                        label={t('contracts.establishments')}
                        htmlFor="establishment_ids"
                        error={errors.establishment_ids}
                        className="sm:col-span-2"
                    >
                        <MultiSelect
                            id="establishment_ids"
                            value={values.establishment_ids}
                            onChange={(ids) => onChange('establishment_ids', ids)}
                            options={filteredEstablishments}
                            placeholder={
                                values.company_id
                                    ? t('contracts.establishmentsPlaceholder')
                                    : t('contracts.selectClientFirst')
                            }
                            invalid={Boolean(errors.establishment_ids)}
                            disabled={!values.company_id}
                        />
                    </Field>
                </div>

                <ContractSchedulePanel
                    iterations={values.iterations}
                    invoicingAggregations={values.invoicing_aggregations}
                    errors={errors}
                    companyId={values.company_id}
                    establishmentOptions={establishmentOptions}
                    workOrderTypeOptions={workOrderTypeOptions}
                    formTemplateOptions={formTemplateOptions}
                    onIterationsChange={(rows) => onChange('iterations', rows)}
                    onAggregationsChange={(rows) => onChange('invoicing_aggregations', rows)}
                />

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

export { emptyAggregation, emptyIteration };
