import { Plus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { MultiSelect } from '@/components/ui/MultiSelect';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import type { UserOption } from '@/support/types/domain/common';
import type {
    ContractInvoicingAggregationFormValues,
    ContractIterationFormValues,
} from '@/support/types/domain/contract';
import type { EstablishmentOption } from '@/support/types/domain/establishment';

type ContractSchedulePanelProps = {
    iterations: ContractIterationFormValues[];
    invoicingAggregations: ContractInvoicingAggregationFormValues[];
    errors: Record<string, string | undefined>;
    companyId: string;
    establishmentOptions: EstablishmentOption[];
    workOrderTypeOptions: UserOption[];
    formTemplateOptions: UserOption[];
    onIterationsChange: (rows: ContractIterationFormValues[]) => void;
    onAggregationsChange: (rows: ContractInvoicingAggregationFormValues[]) => void;
};

function newTempKey(): string {
    return `tmp_${Math.random().toString(36).slice(2, 10)}`;
}

export function emptyIteration(): ContractIterationFormValues {
    return {
        id: null,
        temp_key: newTempKey(),
        subject: '',
        work_order_type_id: '',
        starts_on: '',
        ends_on: '',
        periodicity: 'monthly',
        periodicity_kind: 'basic',
        interval: '',
        weekdays: [],
        month_days: [],
        months: [],
        cost_amount: '0',
        establishment_ids: [],
        form_template_id: '',
        invoicing_aggregation_id: '',
        invoicing_aggregation_temp_key: '',
    };
}

export function emptyAggregation(): ContractInvoicingAggregationFormValues {
    return {
        id: null,
        temp_key: newTempKey(),
        subject: '',
        billing_frequency: 'monthly',
        billing_day: '',
        billing_cycle_start: '',
        per_establishment: false,
    };
}

function toSelectOptions(options: UserOption[]) {
    return options.map((option) => ({
        value: String(option.id),
        label: option.label,
    }));
}

export function ContractSchedulePanel({
    iterations,
    invoicingAggregations,
    errors,
    companyId,
    establishmentOptions,
    workOrderTypeOptions,
    formTemplateOptions,
    onIterationsChange,
    onAggregationsChange,
}: ContractSchedulePanelProps) {
    const { t } = useTranslation();

    const establishmentSelect = useMemo(() => {
        if (!companyId) {
            return [];
        }

        return establishmentOptions
            .filter((option) => String(option.company_id) === companyId)
            .map((option) => ({
                value: String(option.id),
                label: option.label,
            }));
    }, [companyId, establishmentOptions]);

    const aggregationOptions = useMemo(
        () =>
            invoicingAggregations.map((row, index) => ({
                value: row.id ? `id:${row.id}` : `tmp:${row.temp_key}`,
                label: row.subject || t('contracts.aggregationUntitled', { index: index + 1 }),
            })),
        [invoicingAggregations, t],
    );

    const weekdayOptions = useMemo(
        () =>
            [1, 2, 3, 4, 5, 6, 7].map((day) => ({
                value: String(day),
                label: t(`contracts.weekday.${day}`),
            })),
        [t],
    );

    const monthDayOptions = useMemo(
        () =>
            Array.from({ length: 31 }, (_, index) => ({
                value: String(index + 1),
                label: String(index + 1),
            })),
        [],
    );

    const monthOptions = useMemo(
        () =>
            Array.from({ length: 12 }, (_, index) => ({
                value: String(index + 1),
                label: t(`contracts.month.${index + 1}`),
            })),
        [t],
    );

    function updateIteration(index: number, patch: Partial<ContractIterationFormValues>) {
        onIterationsChange(iterations.map((row, i) => (i === index ? { ...row, ...patch } : row)));
    }

    function updateAggregation(index: number, patch: Partial<ContractInvoicingAggregationFormValues>) {
        onAggregationsChange(invoicingAggregations.map((row, i) => (i === index ? { ...row, ...patch } : row)));
    }

    function aggregationValue(row: ContractIterationFormValues): string {
        if (row.invoicing_aggregation_id) {
            return `id:${row.invoicing_aggregation_id}`;
        }

        if (row.invoicing_aggregation_temp_key) {
            return `tmp:${row.invoicing_aggregation_temp_key}`;
        }

        return '';
    }

    function setAggregationLink(index: number, value: string) {
        if (value.startsWith('id:')) {
            updateIteration(index, {
                invoicing_aggregation_id: value.slice(3),
                invoicing_aggregation_temp_key: '',
            });
            return;
        }

        if (value.startsWith('tmp:')) {
            updateIteration(index, {
                invoicing_aggregation_id: '',
                invoicing_aggregation_temp_key: value.slice(4),
            });
            return;
        }

        updateIteration(index, {
            invoicing_aggregation_id: '',
            invoicing_aggregation_temp_key: '',
        });
    }

    return (
        <div className="space-y-8 border-t border-line pt-6">
            <section className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 className="text-sm font-semibold text-ink">{t('contracts.aggregationsTitle')}</h3>
                        <p className="text-sm text-muted">{t('contracts.aggregationsDescription')}</p>
                    </div>
                    <Button type="button" variant="secondary" onClick={() => onAggregationsChange([...invoicingAggregations, emptyAggregation()])}>
                        <Plus className="size-4" aria-hidden />
                        {t('contracts.addAggregation')}
                    </Button>
                </div>

                {invoicingAggregations.length === 0 ? (
                    <p className="text-sm text-muted">{t('contracts.aggregationsEmpty')}</p>
                ) : (
                    invoicingAggregations.map((row, index) => (
                        <div key={row.id ?? row.temp_key} className="space-y-4 rounded-xl border border-line p-4">
                            <div className="flex items-center justify-between gap-3">
                                <p className="text-sm font-medium text-ink">
                                    {t('contracts.aggregationLabel', { index: index + 1 })}
                                </p>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => onAggregationsChange(invoicingAggregations.filter((_, i) => i !== index))}
                                >
                                    <Trash2 className="size-4" aria-hidden />
                                    {t('common.delete')}
                                </Button>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label={t('contracts.aggregationSubject')} error={errors[`invoicing_aggregations.${index}.subject`]}>
                                    <Input
                                        value={row.subject}
                                        onChange={(event) => updateAggregation(index, { subject: event.target.value })}
                                    />
                                </Field>
                                <Field label={t('contracts.billingFrequency')} error={errors[`invoicing_aggregations.${index}.billing_frequency`]}>
                                    <SearchableSelect
                                        value={row.billing_frequency}
                                        options={[
                                            { value: 'monthly', label: t('contracts.frequency.monthly') },
                                            { value: 'bimonthly', label: t('contracts.frequency.bimonthly') },
                                            { value: 'quarterly', label: t('contracts.frequency.quarterly') },
                                            { value: 'annually', label: t('contracts.frequency.annually') },
                                            { value: 'biannually', label: t('contracts.frequency.biannually') },
                                        ]}
                                        onChange={(value) => updateAggregation(index, { billing_frequency: value as ContractInvoicingAggregationFormValues['billing_frequency'] })}
                                    />
                                </Field>
                                <Field label={t('contracts.billingDay')} error={errors[`invoicing_aggregations.${index}.billing_day`]}>
                                    <Input
                                        type="number"
                                        min={1}
                                        max={28}
                                        value={row.billing_day}
                                        onChange={(event) => updateAggregation(index, { billing_day: event.target.value })}
                                    />
                                </Field>
                                <Field label={t('contracts.billingCycleStart')} error={errors[`invoicing_aggregations.${index}.billing_cycle_start`]}>
                                    <Input
                                        type="date"
                                        value={row.billing_cycle_start}
                                        onChange={(event) => updateAggregation(index, { billing_cycle_start: event.target.value })}
                                    />
                                </Field>
                                <label className="flex items-center gap-2 text-sm text-ink sm:col-span-2">
                                    <input
                                        type="checkbox"
                                        checked={row.per_establishment}
                                        onChange={(event) => updateAggregation(index, { per_establishment: event.target.checked })}
                                    />
                                    {t('contracts.perEstablishment')}
                                </label>
                            </div>
                        </div>
                    ))
                )}
            </section>

            <section className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 className="text-sm font-semibold text-ink">{t('contracts.iterationsTitle')}</h3>
                        <p className="text-sm text-muted">{t('contracts.iterationsDescription')}</p>
                    </div>
                    <Button type="button" variant="secondary" onClick={() => onIterationsChange([...iterations, emptyIteration()])}>
                        <Plus className="size-4" aria-hidden />
                        {t('contracts.addIteration')}
                    </Button>
                </div>

                {iterations.length === 0 ? (
                    <p className="text-sm text-muted">{t('contracts.iterationsEmpty')}</p>
                ) : (
                    iterations.map((row, index) => (
                        <div key={row.id ?? row.temp_key} className="space-y-4 rounded-xl border border-line p-4">
                            <div className="flex items-center justify-between gap-3">
                                <p className="text-sm font-medium text-ink">
                                    {t('contracts.iterationLabel', { index: index + 1 })}
                                </p>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => onIterationsChange(iterations.filter((_, i) => i !== index))}
                                >
                                    <Trash2 className="size-4" aria-hidden />
                                    {t('common.delete')}
                                </Button>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label={t('contracts.iterationSubject')} error={errors[`iterations.${index}.subject`]} className="sm:col-span-2">
                                    <Input
                                        value={row.subject}
                                        onChange={(event) => updateIteration(index, { subject: event.target.value })}
                                    />
                                </Field>
                                <Field label={t('contracts.workOrderType')} error={errors[`iterations.${index}.work_order_type_id`]} required>
                                    <SearchableSelect
                                        value={row.work_order_type_id}
                                        options={toSelectOptions(workOrderTypeOptions)}
                                        onChange={(value) => updateIteration(index, { work_order_type_id: value })}
                                    />
                                </Field>
                                <Field label={t('contracts.formTemplate')} error={errors[`iterations.${index}.form_template_id`]}>
                                    <SearchableSelect
                                        value={row.form_template_id}
                                        options={toSelectOptions(formTemplateOptions)}
                                        onChange={(value) => updateIteration(index, { form_template_id: value })}
                                    />
                                </Field>
                                <Field label={t('contracts.startsOn')} error={errors[`iterations.${index}.starts_on`]} required>
                                    <Input
                                        type="date"
                                        value={row.starts_on}
                                        onChange={(event) => updateIteration(index, { starts_on: event.target.value })}
                                    />
                                </Field>
                                <Field label={t('contracts.endsOn')} error={errors[`iterations.${index}.ends_on`]} required>
                                    <Input
                                        type="date"
                                        value={row.ends_on}
                                        onChange={(event) => updateIteration(index, { ends_on: event.target.value })}
                                    />
                                </Field>
                                <Field label={t('contracts.periodicity')} error={errors[`iterations.${index}.periodicity`]} required>
                                    <SearchableSelect
                                        value={row.periodicity}
                                        options={[
                                            { value: 'weekly', label: t('contracts.periodicityWeekly') },
                                            { value: 'monthly', label: t('contracts.periodicityMonthly') },
                                        ]}
                                        onChange={(value) => updateIteration(index, { periodicity: value as ContractIterationFormValues['periodicity'] })}
                                    />
                                </Field>
                                <Field label={t('contracts.periodicityKind')} error={errors[`iterations.${index}.periodicity_kind`]} required>
                                    <SearchableSelect
                                        value={row.periodicity_kind}
                                        options={[
                                            { value: 'basic', label: t('contracts.periodicityBasic') },
                                            { value: 'complex', label: t('contracts.periodicityComplex') },
                                        ]}
                                        onChange={(value) => updateIteration(index, { periodicity_kind: value as ContractIterationFormValues['periodicity_kind'] })}
                                    />
                                </Field>
                                <Field label={t('contracts.interval')} error={errors[`iterations.${index}.interval`]}>
                                    <Input
                                        type="number"
                                        min={1}
                                        value={row.interval}
                                        onChange={(event) => updateIteration(index, { interval: event.target.value })}
                                    />
                                </Field>
                                <Field label={t('contracts.costAmount')} error={errors[`iterations.${index}.cost_amount`]} required>
                                    <Input
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={row.cost_amount}
                                        onChange={(event) => updateIteration(index, { cost_amount: event.target.value })}
                                    />
                                </Field>
                                <Field label={t('contracts.weekdays')} error={errors[`iterations.${index}.weekdays`]} className="sm:col-span-2">
                                    <MultiSelect
                                        value={row.weekdays}
                                        options={weekdayOptions}
                                        onChange={(values) => updateIteration(index, { weekdays: values })}
                                    />
                                </Field>
                                <Field label={t('contracts.monthDays')} error={errors[`iterations.${index}.month_days`]} className="sm:col-span-2">
                                    <MultiSelect
                                        value={row.month_days}
                                        options={monthDayOptions}
                                        onChange={(values) => updateIteration(index, { month_days: values })}
                                    />
                                </Field>
                                <Field label={t('contracts.months')} error={errors[`iterations.${index}.months`]} className="sm:col-span-2">
                                    <MultiSelect
                                        value={row.months}
                                        options={monthOptions}
                                        onChange={(values) => updateIteration(index, { months: values })}
                                    />
                                </Field>
                                <Field label={t('contracts.iterationEstablishments')} error={errors[`iterations.${index}.establishment_ids`]} className="sm:col-span-2">
                                    <MultiSelect
                                        value={row.establishment_ids}
                                        options={establishmentSelect}
                                        onChange={(values) => updateIteration(index, { establishment_ids: values })}
                                        placeholder={companyId ? t('contracts.establishmentsPlaceholder') : t('contracts.selectClientFirst')}
                                    />
                                </Field>
                                <Field label={t('contracts.invoicingAggregation')} error={errors[`iterations.${index}.invoicing_aggregation_id`]} className="sm:col-span-2">
                                    <SearchableSelect
                                        value={aggregationValue(row)}
                                        options={aggregationOptions}
                                        onChange={(value) => setAggregationLink(index, value)}
                                    />
                                </Field>
                            </div>
                        </div>
                    ))
                )}
            </section>
        </div>
    );
}
