import { Fragment, useEffect, useMemo, useRef, useState } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { BaseModal } from '@/components/ui/BaseModal';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { AsyncMultiSelect } from '@/components/ui/AsyncMultiSelect';
import { MultiSelect } from '@/components/ui/MultiSelect';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { Toggle } from '@/components/ui/Toggle';
import type { UserOption } from '@/support/types/domain/common';
import type {
    ContractInvoicingAggregationFormValues,
    ContractIterationFormValues,
} from '@/support/types/domain/contract';
import type { EstablishmentOption } from '@/support/types/domain/establishment';

const BILLING_DAY_MIN = 1;
const BILLING_DAY_MAX = 28;

export type ContractScheduleSection = 'aggregations' | 'iterations';

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
    /** Which schedule blocks to render. Defaults to both. */
    sections?: ContractScheduleSection[];
};

type AggregationEditor = {
    mode: 'create' | 'edit';
    index: number | null;
    draft: ContractInvoicingAggregationFormValues;
};

type IterationEditor = {
    mode: 'create' | 'edit';
    index: number | null;
    draft: ContractIterationFormValues;
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

function optionLabel(options: UserOption[], id: string, fallback: string): string {
    if (!id) {
        return fallback;
    }

    return options.find((option) => String(option.id) === id)?.label ?? fallback;
}

function rowHasErrors(errors: Record<string, string | undefined>, prefix: string): boolean {
    return Object.keys(errors).some((key) => key === prefix || key.startsWith(`${prefix}.`));
}

function rowErrorMessages(errors: Record<string, string | undefined>, prefix: string): string[] {
    return Object.entries(errors)
        .filter(([key, message]) => Boolean(message) && (key === prefix || key.startsWith(`${prefix}.`)))
        .map(([, message]) => message as string);
}

function fieldError(
    errors: Record<string, string | undefined>,
    prefix: string | null,
    field: string,
): string | undefined {
    if (prefix === null) {
        return undefined;
    }

    return errors[`${prefix}.${field}`];
}

function parseBillingDay(value: string): number | null {
    const trimmed = value.trim();

    if (trimmed === '') {
        return null;
    }

    const parsed = Number(trimmed);

    return Number.isInteger(parsed) ? parsed : Number.NaN;
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
    sections = ['aggregations', 'iterations'],
}: ContractSchedulePanelProps) {
    const { t } = useTranslation();
    const showAggregations = sections.includes('aggregations');
    const showIterations = sections.includes('iterations');
    const empty = t('common.emDash');

    const [aggregationEditor, setAggregationEditor] = useState<AggregationEditor | null>(null);
    const [iterationEditor, setIterationEditor] = useState<IterationEditor | null>(null);
    const [aggregationDraftErrors, setAggregationDraftErrors] = useState<Record<string, string>>({});
    const openedAggregationErrorRef = useRef<string | null>(null);

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

    const aggregationErrorPrefix =
        aggregationEditor?.mode === 'edit' && aggregationEditor.index !== null
            ? `invoicing_aggregations.${aggregationEditor.index}`
            : null;
    const iterationErrorPrefix =
        iterationEditor?.mode === 'edit' && iterationEditor.index !== null
            ? `iterations.${iterationEditor.index}`
            : null;

    useEffect(() => {
        if (!showAggregations || aggregationEditor !== null) {
            return;
        }

        const erroredIndex = invoicingAggregations.findIndex((_, index) =>
            rowHasErrors(errors, `invoicing_aggregations.${index}`),
        );

        if (erroredIndex < 0) {
            openedAggregationErrorRef.current = null;
            return;
        }

        const signature = Object.entries(errors)
            .filter(([key]) => key.startsWith(`invoicing_aggregations.${erroredIndex}.`))
            .map(([key, message]) => `${key}:${message}`)
            .join('|');

        if (signature === '' || signature === openedAggregationErrorRef.current) {
            return;
        }

        openedAggregationErrorRef.current = signature;
        setAggregationDraftErrors({});
        setAggregationEditor({
            mode: 'edit',
            index: erroredIndex,
            draft: { ...invoicingAggregations[erroredIndex] },
        });
    }, [aggregationEditor, errors, invoicingAggregations, showAggregations]);

    function openCreateAggregation() {
        setAggregationDraftErrors({});
        setAggregationEditor({
            mode: 'create',
            index: null,
            draft: emptyAggregation(),
        });
    }

    function openEditAggregation(index: number) {
        setAggregationDraftErrors({});
        setAggregationEditor({
            mode: 'edit',
            index,
            draft: { ...invoicingAggregations[index] },
        });
    }

    function saveAggregationEditor() {
        if (!aggregationEditor) {
            return;
        }

        const billingDay = parseBillingDay(aggregationEditor.draft.billing_day);
        const nextErrors: Record<string, string> = {};

        if (billingDay !== null && (Number.isNaN(billingDay) || billingDay < BILLING_DAY_MIN || billingDay > BILLING_DAY_MAX)) {
            nextErrors.billing_day = t('contracts.billingDayRangeError', {
                min: BILLING_DAY_MIN,
                max: BILLING_DAY_MAX,
            });
        }

        if (Object.keys(nextErrors).length > 0) {
            setAggregationDraftErrors(nextErrors);
            return;
        }

        setAggregationDraftErrors({});

        if (aggregationEditor.mode === 'create') {
            onAggregationsChange([...invoicingAggregations, aggregationEditor.draft]);
        } else if (aggregationEditor.index !== null) {
            onAggregationsChange(
                invoicingAggregations.map((row, i) =>
                    i === aggregationEditor.index ? aggregationEditor.draft : row,
                ),
            );
        }

        setAggregationEditor(null);
    }

    function openCreateIteration() {
        setIterationEditor({
            mode: 'create',
            index: null,
            draft: emptyIteration(),
        });
    }

    function openEditIteration(index: number) {
        setIterationEditor({
            mode: 'edit',
            index,
            draft: {
                ...iterations[index],
                weekdays: [...iterations[index].weekdays],
                month_days: [...iterations[index].month_days],
                months: [...iterations[index].months],
                establishment_ids: [...iterations[index].establishment_ids],
            },
        });
    }

    function saveIterationEditor() {
        if (!iterationEditor) {
            return;
        }

        if (iterationEditor.mode === 'create') {
            onIterationsChange([...iterations, iterationEditor.draft]);
        } else if (iterationEditor.index !== null) {
            onIterationsChange(
                iterations.map((row, i) => (i === iterationEditor.index ? iterationEditor.draft : row)),
            );
        }

        setIterationEditor(null);
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

    function setDraftAggregationLink(value: string) {
        if (!iterationEditor) {
            return;
        }

        if (value.startsWith('id:')) {
            setIterationEditor({
                ...iterationEditor,
                draft: {
                    ...iterationEditor.draft,
                    invoicing_aggregation_id: value.slice(3),
                    invoicing_aggregation_temp_key: '',
                },
            });
            return;
        }

        if (value.startsWith('tmp:')) {
            setIterationEditor({
                ...iterationEditor,
                draft: {
                    ...iterationEditor.draft,
                    invoicing_aggregation_id: '',
                    invoicing_aggregation_temp_key: value.slice(4),
                },
            });
            return;
        }

        setIterationEditor({
            ...iterationEditor,
            draft: {
                ...iterationEditor.draft,
                invoicing_aggregation_id: '',
                invoicing_aggregation_temp_key: '',
            },
        });
    }

    function frequencyLabel(value: string): string {
        const key = `contracts.frequency.${value}`;
        const label = t(key);

        return label === key ? value || empty : label;
    }

    function periodicityLabel(value: string): string {
        if (value === 'weekly') {
            return t('contracts.periodicityWeekly');
        }

        if (value === 'monthly') {
            return t('contracts.periodicityMonthly');
        }

        return value || empty;
    }

    return (
        <div className={showAggregations && showIterations ? 'space-y-8 border-t border-line pt-6' : 'space-y-4'}>
            {showAggregations ? (
                <section className="space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 className="text-sm font-semibold text-ink">{t('contracts.aggregationsTitle')}</h3>
                            <p className="text-sm text-muted">{t('contracts.aggregationsDescription')}</p>
                        </div>
                        <Button type="button" variant="secondary" onClick={openCreateAggregation}>
                            <Plus className="size-4" aria-hidden />
                            {t('contracts.addAggregation')}
                        </Button>
                    </div>

                    {invoicingAggregations.length === 0 ? (
                        <p className="text-sm text-muted">{t('contracts.aggregationsEmpty')}</p>
                    ) : (
                        <div className="overflow-x-auto rounded-xl border border-line">
                            <table className="min-w-full text-left text-sm">
                                <thead className="border-b border-line bg-canvas/60 text-xs uppercase tracking-wide text-ink-muted">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">{t('contracts.aggregationSubject')}</th>
                                        <th className="px-4 py-3 font-medium">{t('contracts.billingFrequency')}</th>
                                        <th className="px-4 py-3 font-medium">{t('contracts.billingDay')}</th>
                                        <th className="px-4 py-3 font-medium">{t('contracts.billingCycleStart')}</th>
                                        <th className="px-4 py-3 font-medium">{t('contracts.perEstablishment')}</th>
                                        <th className="px-4 py-3 font-medium text-right">{t('common.actions')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {invoicingAggregations.map((row, index) => {
                                        const prefix = `invoicing_aggregations.${index}`;
                                        const hasErrors = rowHasErrors(errors, prefix);
                                        const messages = rowErrorMessages(errors, prefix);

                                        return (
                                            <Fragment key={row.id ?? row.temp_key}>
                                                <tr
                                                    className={`border-b border-line last:border-b-0 ${
                                                        hasErrors ? 'bg-danger/5' : ''
                                                    }`}
                                                >
                                                    <td className="px-4 py-3 font-medium text-ink">
                                                        {row.subject ||
                                                            t('contracts.aggregationUntitled', {
                                                                index: index + 1,
                                                            })}
                                                    </td>
                                                    <td className="px-4 py-3 text-ink-muted">
                                                        {frequencyLabel(row.billing_frequency)}
                                                    </td>
                                                    <td
                                                        className={`px-4 py-3 ${
                                                            errors[`${prefix}.billing_day`]
                                                                ? 'font-medium text-danger'
                                                                : 'text-ink-muted'
                                                        }`}
                                                    >
                                                        {row.billing_day || empty}
                                                    </td>
                                                    <td className="px-4 py-3 text-ink-muted">
                                                        {row.billing_cycle_start || empty}
                                                    </td>
                                                    <td className="px-4 py-3 text-ink-muted">
                                                        {row.per_establishment
                                                            ? t('common.yes')
                                                            : t('common.no')}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center justify-end gap-1">
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                onClick={() => openEditAggregation(index)}
                                                                aria-label={t('common.edit')}
                                                            >
                                                                <Pencil className="size-4" aria-hidden />
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                onClick={() =>
                                                                    onAggregationsChange(
                                                                        invoicingAggregations.filter(
                                                                            (_, i) => i !== index,
                                                                        ),
                                                                    )
                                                                }
                                                                aria-label={t('common.delete')}
                                                            >
                                                                <Trash2 className="size-4" aria-hidden />
                                                            </Button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                {messages.length > 0 ? (
                                                    <tr className="border-b border-line bg-danger/5 last:border-b-0">
                                                        <td
                                                            colSpan={6}
                                                            className="px-4 py-2 text-sm text-danger"
                                                        >
                                                            {messages.join(' · ')}
                                                        </td>
                                                    </tr>
                                                ) : null}
                                            </Fragment>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            ) : null}

            {showIterations ? (
                <section className="space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 className="text-sm font-semibold text-ink">{t('contracts.iterationsTitle')}</h3>
                            <p className="text-sm text-muted">{t('contracts.iterationsDescription')}</p>
                        </div>
                        <Button type="button" variant="secondary" onClick={openCreateIteration}>
                            <Plus className="size-4" aria-hidden />
                            {t('contracts.addIteration')}
                        </Button>
                    </div>

                    {iterations.length === 0 ? (
                        <p className="text-sm text-muted">{t('contracts.iterationsEmpty')}</p>
                    ) : (
                        <div className="overflow-x-auto rounded-xl border border-line">
                            <table className="min-w-full text-left text-sm">
                                <thead className="border-b border-line bg-canvas/60 text-xs uppercase tracking-wide text-ink-muted">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">{t('contracts.iterationSubject')}</th>
                                        <th className="px-4 py-3 font-medium">{t('contracts.workOrderType')}</th>
                                        <th className="px-4 py-3 font-medium">{t('contracts.startsOn')}</th>
                                        <th className="px-4 py-3 font-medium">{t('contracts.endsOn')}</th>
                                        <th className="px-4 py-3 font-medium">{t('contracts.periodicity')}</th>
                                        <th className="px-4 py-3 font-medium">{t('contracts.costAmount')}</th>
                                        <th className="px-4 py-3 font-medium text-right">{t('common.actions')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {iterations.map((row, index) => (
                                        <tr
                                            key={row.id ?? row.temp_key}
                                            className={`border-b border-line last:border-b-0 ${
                                                rowHasErrors(errors, `iterations.${index}`) ? 'bg-danger/5' : ''
                                            }`}
                                        >
                                            <td className="px-4 py-3 font-medium text-ink">
                                                {row.subject || t('contracts.iterationLabel', { index: index + 1 })}
                                            </td>
                                            <td className="px-4 py-3 text-ink-muted">
                                                {optionLabel(workOrderTypeOptions, row.work_order_type_id, empty)}
                                            </td>
                                            <td className="px-4 py-3 text-ink-muted">{row.starts_on || empty}</td>
                                            <td className="px-4 py-3 text-ink-muted">{row.ends_on || empty}</td>
                                            <td className="px-4 py-3 text-ink-muted">{periodicityLabel(row.periodicity)}</td>
                                            <td className="px-4 py-3 text-ink-muted">{row.cost_amount || empty}</td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        onClick={() => openEditIteration(index)}
                                                        aria-label={t('common.edit')}
                                                    >
                                                        <Pencil className="size-4" aria-hidden />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            onIterationsChange(iterations.filter((_, i) => i !== index))
                                                        }
                                                        aria-label={t('common.delete')}
                                                    >
                                                        <Trash2 className="size-4" aria-hidden />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            ) : null}

            {aggregationEditor ? (
                <BaseModal
                    open
                    size="lg"
                    title={
                        aggregationEditor.mode === 'create'
                            ? t('contracts.addAggregation')
                            : t('contracts.editAggregation')
                    }
                    onClose={() => {
                        setAggregationDraftErrors({});
                        setAggregationEditor(null);
                    }}
                    footer={
                        <>
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => {
                                    setAggregationDraftErrors({});
                                    setAggregationEditor(null);
                                }}
                            >
                                {t('common.cancel')}
                            </Button>
                            <Button type="button" onClick={saveAggregationEditor}>
                                {t('common.save')}
                            </Button>
                        </>
                    }
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label={t('contracts.aggregationSubject')}
                            htmlFor="aggregation_subject"
                            error={fieldError(errors, aggregationErrorPrefix, 'subject')}
                            className="sm:col-span-2"
                        >
                            <Input
                                id="aggregation_subject"
                                value={aggregationEditor.draft.subject}
                                onChange={(event) =>
                                    setAggregationEditor({
                                        ...aggregationEditor,
                                        draft: { ...aggregationEditor.draft, subject: event.target.value },
                                    })
                                }
                            />
                        </Field>
                        <Field
                            label={t('contracts.billingFrequency')}
                            htmlFor="aggregation_billing_frequency"
                            error={fieldError(errors, aggregationErrorPrefix, 'billing_frequency')}
                        >
                            <SearchableSelect
                                id="aggregation_billing_frequency"
                                value={aggregationEditor.draft.billing_frequency}
                                options={[
                                    { value: 'monthly', label: t('contracts.frequency.monthly') },
                                    { value: 'bimonthly', label: t('contracts.frequency.bimonthly') },
                                    { value: 'quarterly', label: t('contracts.frequency.quarterly') },
                                    { value: 'annually', label: t('contracts.frequency.annually') },
                                    { value: 'biannually', label: t('contracts.frequency.biannually') },
                                ]}
                                onChange={(value) =>
                                    setAggregationEditor({
                                        ...aggregationEditor,
                                        draft: {
                                            ...aggregationEditor.draft,
                                            billing_frequency:
                                                value as ContractInvoicingAggregationFormValues['billing_frequency'],
                                        },
                                    })
                                }
                            />
                        </Field>
                        <Field
                            label={t('contracts.billingDay')}
                            htmlFor="aggregation_billing_day"
                            error={
                                aggregationDraftErrors.billing_day ||
                                fieldError(errors, aggregationErrorPrefix, 'billing_day')
                            }
                        >
                            <Input
                                id="aggregation_billing_day"
                                type="number"
                                min={BILLING_DAY_MIN}
                                max={BILLING_DAY_MAX}
                                value={aggregationEditor.draft.billing_day}
                                onChange={(event) => {
                                    setAggregationDraftErrors((current) => {
                                        if (!current.billing_day) {
                                            return current;
                                        }

                                        const { billing_day: _removed, ...rest } = current;

                                        return rest;
                                    });
                                    setAggregationEditor({
                                        ...aggregationEditor,
                                        draft: { ...aggregationEditor.draft, billing_day: event.target.value },
                                    });
                                }}
                            />
                        </Field>
                        <Field
                            label={t('contracts.billingCycleStart')}
                            htmlFor="aggregation_billing_cycle_start"
                            error={fieldError(errors, aggregationErrorPrefix, 'billing_cycle_start')}
                        >
                            <Input
                                id="aggregation_billing_cycle_start"
                                type="date"
                                value={aggregationEditor.draft.billing_cycle_start}
                                onChange={(event) =>
                                    setAggregationEditor({
                                        ...aggregationEditor,
                                        draft: {
                                            ...aggregationEditor.draft,
                                            billing_cycle_start: event.target.value,
                                        },
                                    })
                                }
                            />
                        </Field>
                        <div className="sm:col-span-2">
                            <Toggle
                                name="per_establishment"
                                checked={aggregationEditor.draft.per_establishment}
                                onCheckedChange={(checked) =>
                                    setAggregationEditor({
                                        ...aggregationEditor,
                                        draft: {
                                            ...aggregationEditor.draft,
                                            per_establishment: checked,
                                        },
                                    })
                                }
                                checkedLabel={t('contracts.perEstablishment')}
                                uncheckedLabel={t('contracts.perEstablishmentOff')}
                            />
                        </div>
                    </div>
                </BaseModal>
            ) : null}

            {iterationEditor ? (
                <BaseModal
                    open
                    size="xl"
                    title={
                        iterationEditor.mode === 'create'
                            ? t('contracts.addIteration')
                            : t('contracts.editIteration')
                    }
                    onClose={() => setIterationEditor(null)}
                    footer={
                        <>
                            <Button type="button" variant="secondary" onClick={() => setIterationEditor(null)}>
                                {t('common.cancel')}
                            </Button>
                            <Button type="button" onClick={saveIterationEditor}>
                                {t('common.save')}
                            </Button>
                        </>
                    }
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label={t('contracts.iterationSubject')}
                            htmlFor="iteration_subject"
                            error={fieldError(errors, iterationErrorPrefix, 'subject')}
                            className="sm:col-span-2"
                        >
                            <Input
                                id="iteration_subject"
                                value={iterationEditor.draft.subject}
                                onChange={(event) =>
                                    setIterationEditor({
                                        ...iterationEditor,
                                        draft: { ...iterationEditor.draft, subject: event.target.value },
                                    })
                                }
                            />
                        </Field>
                        <Field
                            label={t('contracts.workOrderType')}
                            htmlFor="iteration_work_order_type_id"
                            error={fieldError(errors, iterationErrorPrefix, 'work_order_type_id')}
                            required
                        >
                            <SearchableSelect
                                id="iteration_work_order_type_id"
                                value={iterationEditor.draft.work_order_type_id}
                                options={toSelectOptions(workOrderTypeOptions)}
                                onChange={(value) =>
                                    setIterationEditor({
                                        ...iterationEditor,
                                        draft: { ...iterationEditor.draft, work_order_type_id: value },
                                    })
                                }
                            />
                        </Field>
                        <Field
                            label={t('contracts.formTemplate')}
                            htmlFor="iteration_form_template_id"
                            error={fieldError(errors, iterationErrorPrefix, 'form_template_id')}
                        >
                            <SearchableSelect
                                id="iteration_form_template_id"
                                value={iterationEditor.draft.form_template_id}
                                options={toSelectOptions(formTemplateOptions)}
                                onChange={(value) =>
                                    setIterationEditor({
                                        ...iterationEditor,
                                        draft: { ...iterationEditor.draft, form_template_id: value },
                                    })
                                }
                            />
                        </Field>
                        <Field
                            label={t('contracts.startsOn')}
                            htmlFor="iteration_starts_on"
                            error={fieldError(errors, iterationErrorPrefix, 'starts_on')}
                            required
                        >
                            <Input
                                id="iteration_starts_on"
                                type="date"
                                value={iterationEditor.draft.starts_on}
                                onChange={(event) =>
                                    setIterationEditor({
                                        ...iterationEditor,
                                        draft: { ...iterationEditor.draft, starts_on: event.target.value },
                                    })
                                }
                            />
                        </Field>
                        <Field
                            label={t('contracts.endsOn')}
                            htmlFor="iteration_ends_on"
                            error={fieldError(errors, iterationErrorPrefix, 'ends_on')}
                            required
                        >
                            <Input
                                id="iteration_ends_on"
                                type="date"
                                value={iterationEditor.draft.ends_on}
                                onChange={(event) =>
                                    setIterationEditor({
                                        ...iterationEditor,
                                        draft: { ...iterationEditor.draft, ends_on: event.target.value },
                                    })
                                }
                            />
                        </Field>
                        <Field
                            label={t('contracts.periodicity')}
                            htmlFor="iteration_periodicity"
                            error={fieldError(errors, iterationErrorPrefix, 'periodicity')}
                            required
                        >
                            <SearchableSelect
                                id="iteration_periodicity"
                                value={iterationEditor.draft.periodicity}
                                options={[
                                    { value: 'weekly', label: t('contracts.periodicityWeekly') },
                                    { value: 'monthly', label: t('contracts.periodicityMonthly') },
                                ]}
                                onChange={(value) =>
                                    setIterationEditor({
                                        ...iterationEditor,
                                        draft: {
                                            ...iterationEditor.draft,
                                            periodicity: value as ContractIterationFormValues['periodicity'],
                                        },
                                    })
                                }
                            />
                        </Field>
                        <Field
                            label={t('contracts.periodicityKind')}
                            htmlFor="iteration_periodicity_kind"
                            error={fieldError(errors, iterationErrorPrefix, 'periodicity_kind')}
                            required
                        >
                            <SearchableSelect
                                id="iteration_periodicity_kind"
                                value={iterationEditor.draft.periodicity_kind}
                                options={[
                                    { value: 'basic', label: t('contracts.periodicityBasic') },
                                    { value: 'complex', label: t('contracts.periodicityComplex') },
                                ]}
                                onChange={(value) =>
                                    setIterationEditor({
                                        ...iterationEditor,
                                        draft: {
                                            ...iterationEditor.draft,
                                            periodicity_kind:
                                                value as ContractIterationFormValues['periodicity_kind'],
                                        },
                                    })
                                }
                            />
                        </Field>
                        <Field
                            label={t('contracts.interval')}
                            htmlFor="iteration_interval"
                            error={fieldError(errors, iterationErrorPrefix, 'interval')}
                        >
                            <Input
                                id="iteration_interval"
                                type="number"
                                min={1}
                                value={iterationEditor.draft.interval}
                                onChange={(event) =>
                                    setIterationEditor({
                                        ...iterationEditor,
                                        draft: { ...iterationEditor.draft, interval: event.target.value },
                                    })
                                }
                            />
                        </Field>
                        <Field
                            label={t('contracts.costAmount')}
                            htmlFor="iteration_cost_amount"
                            error={fieldError(errors, iterationErrorPrefix, 'cost_amount')}
                            required
                        >
                            <Input
                                id="iteration_cost_amount"
                                type="number"
                                min={0}
                                step="0.01"
                                value={iterationEditor.draft.cost_amount}
                                onChange={(event) =>
                                    setIterationEditor({
                                        ...iterationEditor,
                                        draft: { ...iterationEditor.draft, cost_amount: event.target.value },
                                    })
                                }
                            />
                        </Field>
                        <Field
                            label={t('contracts.weekdays')}
                            htmlFor="iteration_weekdays"
                            error={fieldError(errors, iterationErrorPrefix, 'weekdays')}
                            className="sm:col-span-2"
                        >
                            <MultiSelect
                                id="iteration_weekdays"
                                value={iterationEditor.draft.weekdays}
                                options={weekdayOptions}
                                onChange={(values) =>
                                    setIterationEditor({
                                        ...iterationEditor,
                                        draft: { ...iterationEditor.draft, weekdays: values },
                                    })
                                }
                            />
                        </Field>
                        <Field
                            label={t('contracts.monthDays')}
                            htmlFor="iteration_month_days"
                            error={fieldError(errors, iterationErrorPrefix, 'month_days')}
                            className="sm:col-span-2"
                        >
                            <MultiSelect
                                id="iteration_month_days"
                                value={iterationEditor.draft.month_days}
                                options={monthDayOptions}
                                onChange={(values) =>
                                    setIterationEditor({
                                        ...iterationEditor,
                                        draft: { ...iterationEditor.draft, month_days: values },
                                    })
                                }
                            />
                        </Field>
                        <Field
                            label={t('contracts.months')}
                            htmlFor="iteration_months"
                            error={fieldError(errors, iterationErrorPrefix, 'months')}
                            className="sm:col-span-2"
                        >
                            <MultiSelect
                                id="iteration_months"
                                value={iterationEditor.draft.months}
                                options={monthOptions}
                                onChange={(values) =>
                                    setIterationEditor({
                                        ...iterationEditor,
                                        draft: { ...iterationEditor.draft, months: values },
                                    })
                                }
                            />
                        </Field>
                        <Field
                            label={t('contracts.iterationEstablishments')}
                            htmlFor="iteration_establishment_ids"
                            error={fieldError(errors, iterationErrorPrefix, 'establishment_ids')}
                            className="sm:col-span-2"
                        >
                            <AsyncMultiSelect
                                id="iteration_establishment_ids"
                                resource="establishments"
                                value={iterationEditor.draft.establishment_ids}
                                seedOptions={establishmentOptions.filter(
                                    (option) => !companyId || String(option.company_id) === companyId,
                                )}
                                queryParams={{
                                    companyId: companyId ? Number(companyId) : null,
                                    rich: false,
                                }}
                                onChange={(values) =>
                                    setIterationEditor({
                                        ...iterationEditor,
                                        draft: { ...iterationEditor.draft, establishment_ids: values },
                                    })
                                }
                                placeholder={
                                    companyId
                                        ? t('contracts.establishmentsPlaceholder')
                                        : t('contracts.selectClientFirst')
                                }
                                disabled={!companyId}
                            />
                        </Field>
                        <Field
                            label={t('contracts.invoicingAggregation')}
                            htmlFor="iteration_invoicing_aggregation_id"
                            error={fieldError(errors, iterationErrorPrefix, 'invoicing_aggregation_id')}
                            className="sm:col-span-2"
                        >
                            <SearchableSelect
                                id="iteration_invoicing_aggregation_id"
                                value={aggregationValue(iterationEditor.draft)}
                                options={aggregationOptions}
                                onChange={setDraftAggregationLink}
                            />
                        </Field>
                    </div>
                </BaseModal>
            ) : null}
        </div>
    );
}
