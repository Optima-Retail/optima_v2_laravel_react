import { useMemo, type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Trash2 } from 'lucide-react';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { MultiSelect } from '@/components/ui/MultiSelect';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { Select } from '@/components/ui/Select';
import { Toggle } from '@/components/ui/Toggle';
import { RichTextEditor } from '@/components/ui/RichTextEditor';
import { toCompanySelectOptions } from '@/support/companySelect';
import type { CompanyOption, UserOption, WorkOrderArticleOption } from '@/support/types/domain/common';
import type { EstablishmentOption } from '@/support/types/domain/establishment';
import type {
    WorkOrderLineForm,
    WorkOrderTaskForm,
    WorkOrderTechnicianForm,
} from '@/support/types/domain/work-order';
import type { WorkOrderStatusOption } from '@/support/types/domain/work-order-status';

export type WorkOrderFormValues = {
    code: string;
    subject: string;
    reference: string;
    purchase_order: string;
    stage: string;
    status_id: string;
    work_order_type_id: string;
    client_priority_id: string;
    is_urgent: boolean;
    establishment_id: string;
    contract_id: string;
    currency_id: string;
    responsible_user_id: string;
    requester_id: string;
    notes: string;
    internal_notes: string;
    notes_alert: boolean;
    internal_notes_alert: boolean;
    received_at: string;
    intervention_at: string;
    due_at: string;
    collaborator_ids: string[];
    lines: WorkOrderLineForm[];
    technicians: WorkOrderTechnicianForm[];
    tasks: WorkOrderTaskForm[];
};

type WorkOrderFormProps = {
    values: WorkOrderFormValues;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    codeDisabled?: boolean;
    stageLocked?: boolean;
    fieldsLocked?: boolean;
    statusOptions: WorkOrderStatusOption[];
    typeOptions: UserOption[];
    priorityOptions: UserOption[];
    userOptions: UserOption[];
    establishmentOptions: EstablishmentOption[];
    contractOptions?: UserOption[];
    requesterOptions: UserOption[];
    technicianOptions: CompanyOption[];
    articleOptions: WorkOrderArticleOption[];
    sourceLabel?: string | null;
    estimateNum?: string | null;
    workOrderNum?: string | null;
    onChange: (key: keyof WorkOrderFormValues, value: WorkOrderFormValues[keyof WorkOrderFormValues]) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    hideSubmit?: boolean;
    actions?: ReactNode;
};

function toSelectOptions(options: UserOption[]) {
    return options.map((option) => ({
        value: String(option.id),
        label: option.label,
        color: option.color ?? null,
    }));
}

function applyArticleToLine(line: WorkOrderLineForm, articleId: string, articleOptions: WorkOrderArticleOption[]) {
    const article = articleOptions.find((option) => String(option.id) === articleId);

    return {
        ...line,
        article_id: articleId,
        description: article?.description?.trim() ? article.description : line.description,
        unit_price:
            article?.unit_price !== undefined && article.unit_price !== null
                ? String(article.unit_price)
                : line.unit_price,
    };
}

export function emptyWorkOrderLine(): WorkOrderLineForm {
    return {
        article_id: '',
        description: '',
        quantity: '1',
        unit_price: '0',
    };
}

export function emptyWorkOrderTechnician(): WorkOrderTechnicianForm {
    return {
        company_relationship_id: '',
        is_selected: false,
        quote_net_amount: '',
        quoted_at: '',
        quote_total_euros: '',
    };
}

export function emptyWorkOrderTask(): WorkOrderTaskForm {
    return {
        title: '',
        description: '',
        is_completed: false,
    };
}

export function defaultWorkOrderFormValues(
    overrides: Partial<WorkOrderFormValues> = {},
): WorkOrderFormValues {
    return {
        code: '',
        subject: '',
        reference: '',
        purchase_order: '',
        stage: 'estimate',
        status_id: '',
        work_order_type_id: '',
        client_priority_id: '',
        is_urgent: false,
        establishment_id: '',
        contract_id: '',
        currency_id: '',
        responsible_user_id: '',
        requester_id: '',
        notes: '',
        internal_notes: '',
        notes_alert: false,
        internal_notes_alert: false,
        received_at: '',
        intervention_at: '',
        due_at: '',
        collaborator_ids: [],
        lines: [],
        technicians: [],
        tasks: [],
        ...overrides,
    };
}

export function WorkOrderForm({
    values,
    errors,
    processing,
    codeDisabled = false,
    stageLocked = false,
    fieldsLocked = false,
    statusOptions,
    typeOptions,
    priorityOptions,
    userOptions,
    establishmentOptions,
    contractOptions = [],
    requesterOptions,
    technicianOptions,
    articleOptions,
    sourceLabel,
    estimateNum = null,
    workOrderNum = null,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    hideSubmit = false,
    actions,
}: WorkOrderFormProps) {
    const { t } = useTranslation();
    const locked = fieldsLocked;
    const selectedStatus = statusOptions.find((option) => String(option.id) === values.status_id);

    const establishmentSelect = useMemo(
        () =>
            establishmentOptions.map((option) => ({
                value: String(option.id),
                label: option.label,
            })),
        [establishmentOptions],
    );

    const contractSelect = useMemo(
        () =>
            contractOptions.map((option) => ({
                value: String(option.id),
                label: option.label,
            })),
        [contractOptions],
    );

    return (
        <FieldHelpScope table="work_orders">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                {sourceLabel ? (
                    <p className="rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-ink-muted">
                        {t('workOrders.sourceDocument')}: {sourceLabel}
                    </p>
                ) : null}

                {locked ? (
                    <p className="rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-ink-muted">
                        {t('workOrders.fieldsLockedHint')}
                    </p>
                ) : null}

                <div className="grid gap-5 sm:grid-cols-2">
                    <Field label={t('workOrders.subject')} htmlFor="subject" error={errors.subject} className="sm:col-span-2" required>
                        <Input
                            id="subject"
                            value={values.subject}
                            invalid={Boolean(errors.subject)}
                            disabled={locked}
                            onChange={(event) => onChange('subject', event.target.value)}
                        />
                    </Field>

                    <Field label={t('common.code')} htmlFor="code" error={errors.code}>
                        <Input
                            id="code"
                            value={values.code}
                            invalid={Boolean(errors.code)}
                            disabled={codeDisabled || locked}
                            readOnly={codeDisabled || locked}
                            onChange={(event) => onChange('code', event.target.value)}
                        />
                        {codeDisabled ? (
                            <p className="text-xs text-ink-muted">{t('workOrders.codeAutomaticHint')}</p>
                        ) : null}
                    </Field>

                    {estimateNum && values.stage === 'work_order' ? (
                        <Field label={t('workOrders.estimateNum')} htmlFor="estimate_num_readonly">
                            <Input id="estimate_num_readonly" value={estimateNum} readOnly disabled />
                        </Field>
                    ) : null}

                    {workOrderNum && values.stage === 'estimate' ? (
                        <Field label={t('workOrders.workOrderNum')} htmlFor="work_order_num_readonly">
                            <Input id="work_order_num_readonly" value={workOrderNum} readOnly disabled />
                        </Field>
                    ) : null}

                    <Field label={t('workOrders.stage')} htmlFor="stage" error={errors.stage} required className={stageLocked ? 'hidden' : undefined}>
                        <Select
                            id="stage"
                            value={values.stage}
                            invalid={Boolean(errors.stage)}
                            disabled={stageLocked || locked}
                            onChange={(event) => onChange('stage', event.target.value)}
                        >
                            <option value="estimate">{t('workOrders.stages.estimate')}</option>
                            <option value="work_order">{t('workOrders.stages.work_order')}</option>
                        </Select>
                    </Field>

                    <Field label={t('workOrders.status')} htmlFor="status_id" error={errors.status_id ?? errors.status_justification} required>
                        <SearchableSelect
                            id="status_id"
                            value={values.status_id}
                            invalid={Boolean(errors.status_id)}
                            onChange={(value) => onChange('status_id', value)}
                            emptyLabel={t('common.select')}
                            options={toSelectOptions(statusOptions)}
                        />
                        {selectedStatus?.confirms_estimate ? (
                            <p className="text-xs text-ink-muted">{t('workOrders.approveHint')}</p>
                        ) : null}
                        {selectedStatus?.rejects_to_estimate ? (
                            <p className="text-xs text-ink-muted">{t('workOrders.rejectHint')}</p>
                        ) : null}
                    </Field>

                    <Field label={t('workOrders.establishment')} htmlFor="establishment_id" error={errors.establishment_id} required>
                        <SearchableSelect
                            id="establishment_id"
                            value={values.establishment_id}
                            invalid={Boolean(errors.establishment_id)}
                            disabled={locked}
                            onChange={(value) => onChange('establishment_id', value)}
                            emptyLabel={t('common.select')}
                            options={establishmentSelect}
                        />
                    </Field>

                    <Field label={t('workOrders.contract')} htmlFor="contract_id" error={errors.contract_id}>
                        <SearchableSelect
                            id="contract_id"
                            value={values.contract_id}
                            invalid={Boolean(errors.contract_id)}
                            disabled={locked}
                            onChange={(value) => onChange('contract_id', value)}
                            emptyLabel={t('common.select')}
                            options={contractSelect}
                        />
                    </Field>

                    <Field label={t('workOrders.type')} htmlFor="work_order_type_id" error={errors.work_order_type_id}>
                        <SearchableSelect
                            id="work_order_type_id"
                            value={values.work_order_type_id}
                            invalid={Boolean(errors.work_order_type_id)}
                            disabled={locked}
                            onChange={(value) => onChange('work_order_type_id', value)}
                            emptyLabel={t('common.select')}
                            options={toSelectOptions(typeOptions)}
                        />
                    </Field>

                    <Field label={t('workOrders.priority')} htmlFor="client_priority_id" error={errors.client_priority_id}>
                        <SearchableSelect
                            id="client_priority_id"
                            value={values.client_priority_id}
                            invalid={Boolean(errors.client_priority_id)}
                            disabled={locked}
                            onChange={(value) => onChange('client_priority_id', value)}
                            emptyLabel={t('common.select')}
                            options={toSelectOptions(priorityOptions)}
                        />
                    </Field>

                    <Field label={t('workOrders.responsibleUser')} htmlFor="responsible_user_id" error={errors.responsible_user_id}>
                        <SearchableSelect
                            id="responsible_user_id"
                            value={values.responsible_user_id}
                            invalid={Boolean(errors.responsible_user_id)}
                            disabled={locked}
                            onChange={(value) => onChange('responsible_user_id', value)}
                            emptyLabel={t('common.select')}
                            options={toSelectOptions(userOptions)}
                        />
                    </Field>

                    <Field label={t('workOrders.requester')} htmlFor="requester_id" error={errors.requester_id}>
                        <SearchableSelect
                            id="requester_id"
                            value={values.requester_id}
                            invalid={Boolean(errors.requester_id)}
                            disabled={locked}
                            onChange={(value) => onChange('requester_id', value)}
                            emptyLabel={t('common.select')}
                            options={toSelectOptions(requesterOptions)}
                        />
                    </Field>

                    <Field label={t('workOrders.reference')} htmlFor="reference" error={errors.reference}>
                        <Input
                            id="reference"
                            value={values.reference}
                            invalid={Boolean(errors.reference)}
                            disabled={locked}
                            onChange={(event) => onChange('reference', event.target.value)}
                        />
                    </Field>

                    <Field label={t('workOrders.purchaseOrder')} htmlFor="purchase_order" error={errors.purchase_order}>
                        <Input
                            id="purchase_order"
                            value={values.purchase_order}
                            invalid={Boolean(errors.purchase_order)}
                            disabled={locked}
                            onChange={(event) => onChange('purchase_order', event.target.value)}
                        />
                    </Field>

                    <Field label={t('workOrders.receivedAt')} htmlFor="received_at" error={errors.received_at}>
                        <Input
                            id="received_at"
                            type="datetime-local"
                            value={values.received_at}
                            invalid={Boolean(errors.received_at)}
                            disabled={locked}
                            onChange={(event) => onChange('received_at', event.target.value)}
                        />
                    </Field>

                    <Field label={t('workOrders.dueAt')} htmlFor="due_at" error={errors.due_at}>
                        <Input
                            id="due_at"
                            type="datetime-local"
                            value={values.due_at}
                            invalid={Boolean(errors.due_at)}
                            disabled={locked}
                            onChange={(event) => onChange('due_at', event.target.value)}
                        />
                    </Field>

                    <Field label={t('workOrders.interventionAt')} htmlFor="intervention_at" error={errors.intervention_at}>
                        <Input
                            id="intervention_at"
                            type="datetime-local"
                            value={values.intervention_at}
                            invalid={Boolean(errors.intervention_at)}
                            disabled={locked}
                            onChange={(event) => onChange('intervention_at', event.target.value)}
                        />
                    </Field>
                </div>

                <div className="space-y-1">
                    <p className="text-sm font-semibold text-ink">{t('workOrders.urgent')}</p>
                    <Toggle
                        name="is_urgent"
                        checked={values.is_urgent}
                        disabled={locked}
                        onCheckedChange={(checked) => onChange('is_urgent', checked)}
                        checkedLabel={t('workOrders.urgent')}
                        uncheckedLabel={t('workOrders.notUrgent')}
                    />
                </div>

                <Field label={t('workOrders.collaborators')} htmlFor="collaborator_ids" error={errors.collaborator_ids}>
                    <MultiSelect
                        id="collaborator_ids"
                        value={values.collaborator_ids}
                        disabled={locked}
                        onChange={(next) => onChange('collaborator_ids', next)}
                        options={toSelectOptions(userOptions)}
                        placeholder={t('workOrders.collaboratorsPlaceholder')}
                    />
                </Field>

                <Field label={t('workOrders.notes')} htmlFor="notes" error={errors.notes} className="sm:col-span-2">
                    <RichTextEditor
                        id="notes"
                        value={values.notes}
                        invalid={Boolean(errors.notes)}
                        disabled={locked}
                        onChange={(html) => onChange('notes', html)}
                    />
                </Field>

                <Field
                    label={t('workOrders.internalNotes')}
                    htmlFor="internal_notes"
                    error={errors.internal_notes}
                    className="sm:col-span-2"
                >
                    <RichTextEditor
                        id="internal_notes"
                        value={values.internal_notes}
                        invalid={Boolean(errors.internal_notes)}
                        disabled={locked}
                        onChange={(html) => onChange('internal_notes', html)}
                    />
                </Field>

                <Toggle
                    name="notes_alert"
                    checked={values.notes_alert}
                    disabled={locked}
                    onCheckedChange={(checked) => onChange('notes_alert', checked)}
                    checkedLabel={t('workOrders.notesAlert')}
                    uncheckedLabel={t('workOrders.notesAlertOff')}
                />
                <Toggle
                    name="internal_notes_alert"
                    checked={values.internal_notes_alert}
                    disabled={locked}
                    onCheckedChange={(checked) => onChange('internal_notes_alert', checked)}
                    checkedLabel={t('workOrders.internalNotesAlert')}
                    uncheckedLabel={t('workOrders.internalNotesAlertOff')}
                />

                <div className="space-y-3 border-t border-line pt-5">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-sm font-semibold text-ink">{t('workOrders.lines')}</h2>
                        <Button
                            type="button"
                            variant="secondary"
                            disabled={locked}
                            onClick={() => onChange('lines', [...values.lines, emptyWorkOrderLine()])}
                        >
                            <Plus className="size-3.5" aria-hidden />
                            {t('workOrders.addLine')}
                        </Button>
                    </div>
                    {values.lines.length === 0 ? (
                        <p className="text-sm text-ink-muted">{t('workOrders.linesEmpty')}</p>
                    ) : (
                        <div className="space-y-3">
                            {values.lines.map((line, index) => (
                                <div key={line.id ?? `new-${index}`} className="grid gap-3 rounded-xl border border-line p-3 sm:grid-cols-12">
                                    <Field label={t('workOrders.article')} htmlFor={`line-article-${index}`} className="sm:col-span-3" error={errors[`lines.${index}.article_id`]}>
                                        <SearchableSelect
                                            id={`line-article-${index}`}
                                            value={line.article_id}
                                            disabled={locked}
                                            onChange={(value) => {
                                                const next = [...values.lines];
                                                next[index] = applyArticleToLine(line, value, articleOptions);
                                                onChange('lines', next);
                                            }}
                                            emptyLabel={t('common.select')}
                                            options={toSelectOptions(articleOptions)}
                                        />
                                    </Field>
                                    <Field label={t('workOrders.lineDescription')} htmlFor={`line-description-${index}`} className="sm:col-span-4" error={errors[`lines.${index}.description`]}>
                                        <Input
                                            id={`line-description-${index}`}
                                            value={line.description}
                                            disabled={locked}
                                            onChange={(event) => {
                                                const next = [...values.lines];
                                                next[index] = { ...line, description: event.target.value };
                                                onChange('lines', next);
                                            }}
                                        />
                                    </Field>
                                    <Field label={t('workOrders.quantity')} htmlFor={`line-qty-${index}`} className="sm:col-span-2" error={errors[`lines.${index}.quantity`]}>
                                        <Input
                                            id={`line-qty-${index}`}
                                            type="number"
                                            min={0}
                                            step="0.001"
                                            value={line.quantity}
                                            disabled={locked}
                                            onChange={(event) => {
                                                const next = [...values.lines];
                                                next[index] = { ...line, quantity: event.target.value };
                                                onChange('lines', next);
                                            }}
                                        />
                                    </Field>
                                    <Field label={t('workOrders.unitPrice')} htmlFor={`line-price-${index}`} className="sm:col-span-2" error={errors[`lines.${index}.unit_price`]}>
                                        <Input
                                            id={`line-price-${index}`}
                                            type="number"
                                            step="0.01"
                                            value={line.unit_price}
                                            disabled={locked}
                                            onChange={(event) => {
                                                const next = [...values.lines];
                                                next[index] = { ...line, unit_price: event.target.value };
                                                onChange('lines', next);
                                            }}
                                        />
                                    </Field>
                                    <div className="flex items-end sm:col-span-1">
                                        <Button
                                            type="button"
                                            variant="danger"
                                            disabled={locked}
                                            onClick={() => onChange('lines', values.lines.filter((_, row) => row !== index))}
                                        >
                                            <Trash2 className="size-3.5" aria-hidden />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div className="space-y-3 border-t border-line pt-5">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-sm font-semibold text-ink">{t('workOrders.technicians')}</h2>
                        <Button
                            type="button"
                            variant="secondary"
                            disabled={locked}
                            onClick={() => onChange('technicians', [...values.technicians, emptyWorkOrderTechnician()])}
                        >
                            <Plus className="size-3.5" aria-hidden />
                            {t('workOrders.addTechnician')}
                        </Button>
                    </div>
                    {values.technicians.length === 0 ? (
                        <p className="text-sm text-ink-muted">{t('workOrders.techniciansEmpty')}</p>
                    ) : (
                        <div className="space-y-3">
                            {values.technicians.map((technician, index) => (
                                <div key={technician.id ?? `tech-${index}`} className="grid gap-3 rounded-xl border border-line p-3 sm:grid-cols-12">
                                    <Field label={t('workOrders.technician')} htmlFor={`tech-${index}`} className="sm:col-span-5" error={errors[`technicians.${index}.company_relationship_id`]}>
                                        <SearchableSelect
                                            id={`tech-${index}`}
                                            value={technician.company_relationship_id}
                                            disabled={locked}
                                            onChange={(value) => {
                                                const next = [...values.technicians];
                                                next[index] = { ...technician, company_relationship_id: value };
                                                onChange('technicians', next);
                                            }}
                                            emptyLabel={t('common.select')}
                                            options={toCompanySelectOptions(technicianOptions)}
                                        />
                                    </Field>
                                    <Field label={t('workOrders.quoteNet')} htmlFor={`tech-quote-${index}`} className="sm:col-span-3" error={errors[`technicians.${index}.quote_net_amount`]}>
                                        <Input
                                            id={`tech-quote-${index}`}
                                            type="number"
                                            step="0.01"
                                            value={technician.quote_net_amount}
                                            disabled={locked}
                                            onChange={(event) => {
                                                const next = [...values.technicians];
                                                const net = event.target.value;
                                                next[index] = {
                                                    ...technician,
                                                    quote_net_amount: net,
                                                    quote_total_euros: net,
                                                };
                                                onChange('technicians', next);
                                            }}
                                        />
                                    </Field>
                                    <div className="flex items-end sm:col-span-3">
                                        <Toggle
                                            name={`technicians.${index}.is_selected`}
                                            checked={technician.is_selected}
                                            disabled={locked}
                                            onCheckedChange={(checked) => {
                                                const next = [...values.technicians];
                                                next[index] = { ...technician, is_selected: checked };
                                                onChange('technicians', next);
                                            }}
                                            checkedLabel={t('workOrders.selected')}
                                            uncheckedLabel={t('workOrders.notSelected')}
                                        />
                                    </div>
                                    <div className="flex items-end sm:col-span-1">
                                        <Button
                                            type="button"
                                            variant="danger"
                                            disabled={locked}
                                            onClick={() => onChange('technicians', values.technicians.filter((_, row) => row !== index))}
                                        >
                                            <Trash2 className="size-3.5" aria-hidden />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div className="flex flex-wrap items-center justify-end gap-2 border-t border-line pt-4">
                    {actions}
                    {!hideSubmit ? (
                        <Button type="submit" loading={processing}>
                            {submitIcon}
                            {submitLabel}
                        </Button>
                    ) : null}
                </div>
            </form>
        </FieldHelpScope>
    );
}
