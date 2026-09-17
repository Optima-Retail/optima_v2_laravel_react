import { useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Search, Trash2 } from 'lucide-react';
import { CompanyOptionLabel } from '@/components/companies/CompanyOptionLabel';
import { TechnicianSearchModal } from '@/components/estimates/TechnicianSearchModal';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { MultiSelect } from '@/components/ui/MultiSelect';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { Select } from '@/components/ui/Select';
import { Toggle } from '@/components/ui/Toggle';
import { RichTextEditor } from '@/components/ui/RichTextEditor';
import { cn } from '@/support/cn';
import { optionColorStyle } from '@/support/color';
import type { CompanyOption, UserOption, WorkOrderArticleOption } from '@/support/types/domain/common';
import type { EstablishmentOption } from '@/support/types/domain/establishment';
import type {
    WorkOrderLineForm,
    WorkOrderTaskForm,
    WorkOrderTechnicianForm,
} from '@/support/types/domain/work-order';
import type { WorkOrderStatusOption } from '@/support/types/domain/work-order-status';

export type WorkOrderPriorityOption = UserOption & {
    company_ids?: number[];
};

export type WorkOrderFormSection = 'details' | 'tasks' | 'technicians' | 'notes' | 'lines' | 'checklists' | 'all';

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
    sla_at: string;
    sla_justification: string;
    collaborator_ids: string[];
    lines: WorkOrderLineForm[];
    technicians: WorkOrderTechnicianForm[];
    tasks: WorkOrderTaskForm[];
};

type WorkOrderFormProps = {
    mode?: 'create' | 'edit';
    section?: WorkOrderFormSection;
    values: WorkOrderFormValues;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    codeDisabled?: boolean;
    stageLocked?: boolean;
    fieldsLocked?: boolean;
    /** Prod: lifecycle >= FINALIZADA_PENDIENTE (4) → intervention shown as non-editable. */
    interventionLocked?: boolean;
    /** Prod: open lifecycle + permission → establishment can change; otherwise name-only display. */
    canChangeEstablishment?: boolean;
    statusOptions: WorkOrderStatusOption[];
    typeOptions: UserOption[];
    priorityOptions: WorkOrderPriorityOption[];
    userOptions: UserOption[];
    establishmentOptions: EstablishmentOption[];
    contractOptions?: UserOption[];
    requesterOptions: UserOption[];
    technicianOptions: CompanyOption[];
    articleOptions: WorkOrderArticleOption[];
    technicianStatusOptions?: UserOption[];
    attendanceTypeOptions?: UserOption[];
    checklistItems?: Array<{ id: number; name: string; completed: boolean }>;
    sourceLabel?: string | null;
    estimateNum?: string | null;
    workOrderNum?: string | null;
    currencyLabel?: string | null;
    /** Prod fecha_creacion — display only. */
    createdAt?: string | null;
    /** Prod fecha_cierre — display only. */
    closedAt?: string | null;
    /** Show contract selector (not on prod OT DetallesV2 — hidden by default). */
    showContract?: boolean;
    /** Show technician quote amounts (estimate-oriented; hidden on pure OT by default). */
    showTechnicianQuotes?: boolean;
    onChange: (key: keyof WorkOrderFormValues, value: WorkOrderFormValues[keyof WorkOrderFormValues]) => void;
    onChecklistChange?: (checklistId: number, completed: boolean) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    hideSubmit?: boolean;
    actions?: ReactNode;
};

function Section({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
    return (
        <section className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <div>
                <h2 className="text-base font-semibold text-ink">{title}</h2>
                {description ? <p className="mt-1 text-sm text-ink-muted">{description}</p> : null}
            </div>
            {children}
        </section>
    );
}

/** Prod `input-no-editable`: gray box showing the value when the field is not editable. */
function ReadonlyValue({
    value,
    isDate = false,
    color = null,
}: {
    value: string;
    isDate?: boolean;
    color?: string | null;
}) {
    const colorStyle = optionColorStyle(color);

    return (
        <div
            className={cn(
                'flex h-8 w-full items-center overflow-hidden rounded-lg border border-line bg-canvas px-3 text-sm text-ink',
                isDate && 'justify-center tabular-nums',
            )}
            style={colorStyle}
        >
            <span className="truncate">{value || '—'}</span>
        </div>
    );
}

function formatDateTimeDisplay(value: string): string {
    if (!value) {
        return '';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value.replace('T', ' ');
    }

    const pad = (n: number) => String(n).padStart(2, '0');

    return `${pad(parsed.getDate())}-${pad(parsed.getMonth() + 1)}-${parsed.getFullYear()} ${pad(parsed.getHours())}:${pad(parsed.getMinutes())}`;
}

function optionLabel(options: Array<{ id: number; label: string }>, value: string): string {
    if (!value) {
        return '';
    }

    return options.find((option) => String(option.id) === value)?.label ?? value;
}

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
        status_id: '',
        attendance_confirmation_type_id: '',
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
        sla_at: '',
        sla_justification: '',
        collaborator_ids: [],
        lines: [],
        technicians: [],
        tasks: [],
        ...overrides,
    };
}

export function WorkOrderForm({
    mode = 'edit',
    section = 'all',
    values,
    errors,
    processing,
    codeDisabled = false,
    stageLocked = false,
    fieldsLocked = false,
    interventionLocked = false,
    canChangeEstablishment = true,
    statusOptions,
    typeOptions,
    priorityOptions,
    userOptions,
    establishmentOptions,
    contractOptions = [],
    requesterOptions,
    technicianOptions,
    articleOptions,
    technicianStatusOptions = [],
    attendanceTypeOptions = [],
    checklistItems = [],
    sourceLabel,
    estimateNum = null,
    workOrderNum = null,
    currencyLabel = null,
    createdAt = null,
    closedAt = null,
    showContract = false,
    showTechnicianQuotes = false,
    onChange,
    onChecklistChange,
    onSubmit,
    submitLabel,
    submitIcon,
    hideSubmit = false,
    actions,
}: WorkOrderFormProps) {
    const { t } = useTranslation();
    // Prod closed-without-update-closed: body locked; status / reference / PO stay editable.
    const bodyLocked = fieldsLocked;
    const establishmentLocked = bodyLocked || !canChangeEstablishment;
    const isCreate = mode === 'create';
    const activeSection = section;
    const showDetails = activeSection === 'all' || activeSection === 'details';
    const showTasks = !isCreate && (activeSection === 'all' || activeSection === 'tasks');
    const showTechnicians = !isCreate && (activeSection === 'all' || activeSection === 'technicians');
    const showNotes = !isCreate && (activeSection === 'all' || activeSection === 'notes');
    const showLines = !isCreate && (activeSection === 'all' || activeSection === 'lines');
    const showChecklists = !isCreate && (activeSection === 'all' || activeSection === 'checklists');
    const selectedStatus = statusOptions.find((option) => String(option.id) === values.status_id);
    // Prod: lifecycle >= FINALIZADA_PENDIENTE (4) → intervention as input-no-editable.
    const interventionFieldLocked =
        bodyLocked ||
        interventionLocked ||
        (selectedStatus?.lifecycle != null && selectedStatus.lifecycle >= 4);
    // Prod DetallesV2: EN_PROGRESO (status id 18) hides technician add/edit.
    const techniciansLocked =
        bodyLocked ||
        Number(values.status_id) === 18 ||
        /en\s*progreso/i.test(selectedStatus?.label ?? '');
    const [technicianPickerIndex, setTechnicianPickerIndex] = useState<number | null>(null);
    const [extraTechnicianOptions, setExtraTechnicianOptions] = useState<CompanyOption[]>([]);

    const selectedEstablishment = useMemo(
        () => establishmentOptions.find((option) => String(option.id) === values.establishment_id) ?? null,
        [establishmentOptions, values.establishment_id],
    );

    // Prod: priorities are the client's configured list (company_priority), not the global catalog.
    const filteredPriorityOptions = useMemo(() => {
        const companyId = selectedEstablishment?.company_id;

        if (!companyId) {
            return [];
        }

        return priorityOptions.filter((option) => {
            const ids = (option.company_ids ?? []).map(Number);

            return ids.includes(Number(companyId));
        });
    }, [priorityOptions, selectedEstablishment?.company_id]);

    const selectedPriority = useMemo(
        () =>
            filteredPriorityOptions.find((option) => String(option.id) === values.client_priority_id) ??
            priorityOptions.find((option) => String(option.id) === values.client_priority_id) ??
            null,
        [filteredPriorityOptions, priorityOptions, values.client_priority_id],
    );

    const resolvedTechnicianOptions = useMemo(() => {
        const byId = new Map<number, CompanyOption>();
        [...technicianOptions, ...extraTechnicianOptions].forEach((option) => {
            byId.set(option.id, option);
        });
        return Array.from(byId.values());
    }, [extraTechnicianOptions, technicianOptions]);

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

    function handleEstablishmentChange(value: string) {
        onChange('establishment_id', value);
        const option = establishmentOptions.find((item) => String(item.id) === value);
        if (option?.currency_id) {
            onChange('currency_id', String(option.currency_id));
        }

        // Clear priority when it does not belong to the new client's list.
        const companyId = option?.company_id;
        if (!companyId || !values.client_priority_id) {
            if (!companyId) {
                onChange('client_priority_id', '');
            }
            return;
        }

        const stillValid = priorityOptions.some((priority) => {
            if (String(priority.id) !== values.client_priority_id) {
                return false;
            }

            const ids = (priority.company_ids ?? []).map(Number);

            return ids.includes(Number(companyId));
        });

        if (!stillValid) {
            onChange('client_priority_id', '');
        }
    }

    // Keep save/actions inside the same card as the fields (BrandForm / UserForm style).
    const actionsSection = showChecklists
        ? 'checklists'
        : showLines
          ? 'lines'
          : showNotes
            ? 'notes'
            : showTechnicians
              ? 'technicians'
              : showTasks
                ? 'tasks'
                : showDetails
                  ? 'details'
                  : null;

    const formActions =
        actions || !hideSubmit ? (
            <div className="flex flex-wrap items-center justify-end gap-2 border-t border-line pt-4">
                {actions}
                {!hideSubmit ? (
                    <Button type="submit" loading={processing}>
                        {submitIcon}
                        {submitLabel}
                    </Button>
                ) : null}
            </div>
        ) : null;

    return (
        <FieldHelpScope table="work_orders">
            <form onSubmit={onSubmit} className="space-y-5">
                {showDetails ? (
                <Section title={t('workOrders.sectionDetails')} description={t('workOrders.sectionDetailsDescription')}>
                {sourceLabel ? (
                    <p className="rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-ink-muted">
                        {t('workOrders.sourceDocument')}: {sourceLabel}
                    </p>
                ) : null}

                {bodyLocked ? (
                    <p className="rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-ink-muted">
                        {t('workOrders.fieldsLockedHint')}
                    </p>
                ) : null}

                <div className="grid gap-5 sm:grid-cols-2">
                    <Field label={t('workOrders.subject')} htmlFor="subject" error={errors.subject} className="sm:col-span-2" required>
                        {bodyLocked ? (
                            <ReadonlyValue value={values.subject} />
                        ) : (
                            <Input
                                id="subject"
                                value={values.subject}
                                invalid={Boolean(errors.subject)}
                                onChange={(event) => onChange('subject', event.target.value)}
                            />
                        )}
                    </Field>

                    <Field label={t('common.code')} htmlFor="code" error={errors.code}>
                        <Input
                            id="code"
                            value={values.code}
                            invalid={Boolean(errors.code)}
                            disabled={codeDisabled || bodyLocked}
                            readOnly={codeDisabled || bodyLocked}
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
                            disabled={stageLocked || bodyLocked}
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
                        {establishmentLocked && !isCreate ? (
                            <ReadonlyValue value={selectedEstablishment?.label || values.establishment_id} />
                        ) : (
                            <SearchableSelect
                                id="establishment_id"
                                value={values.establishment_id}
                                invalid={Boolean(errors.establishment_id)}
                                onChange={handleEstablishmentChange}
                                emptyLabel={t('common.select')}
                                options={establishmentSelect}
                            />
                        )}
                    </Field>

                    {selectedEstablishment ? (
                        <>
                            <Field label={t('workOrders.client')} htmlFor="derived_client">
                                <Input id="derived_client" value={selectedEstablishment.company_name ?? ''} readOnly disabled />
                            </Field>
                            <Field label={t('workOrders.brand')} htmlFor="derived_brand">
                                <Input id="derived_brand" value={selectedEstablishment.brand_name ?? ''} readOnly disabled />
                            </Field>
                        </>
                    ) : null}

                    {showContract ? (
                        <Field label={t('workOrders.contract')} htmlFor="contract_id" error={errors.contract_id}>
                            {bodyLocked ? (
                                <ReadonlyValue value={optionLabel(contractOptions, values.contract_id)} />
                            ) : (
                                <SearchableSelect
                                    id="contract_id"
                                    value={values.contract_id}
                                    invalid={Boolean(errors.contract_id)}
                                    onChange={(value) => onChange('contract_id', value)}
                                    emptyLabel={t('common.select')}
                                    options={contractSelect}
                                />
                            )}
                        </Field>
                    ) : null}

                    <Field label={t('workOrders.type')} htmlFor="work_order_type_id" error={errors.work_order_type_id}>
                        {bodyLocked ? (
                            <ReadonlyValue value={optionLabel(typeOptions, values.work_order_type_id)} />
                        ) : (
                            <SearchableSelect
                                id="work_order_type_id"
                                value={values.work_order_type_id}
                                invalid={Boolean(errors.work_order_type_id)}
                                onChange={(value) => onChange('work_order_type_id', value)}
                                emptyLabel={t('common.select')}
                                options={toSelectOptions(typeOptions)}
                            />
                        )}
                    </Field>

                    <Field label={t('workOrders.priority')} htmlFor="client_priority_id" error={errors.client_priority_id}>
                        {bodyLocked ? (
                            <ReadonlyValue
                                value={optionLabel(priorityOptions, values.client_priority_id)}
                                color={selectedPriority?.color}
                            />
                        ) : (
                            <SearchableSelect
                                id="client_priority_id"
                                value={values.client_priority_id}
                                invalid={Boolean(errors.client_priority_id)}
                                disabled={!selectedEstablishment}
                                onChange={(value) => onChange('client_priority_id', value)}
                                emptyLabel={
                                    selectedEstablishment
                                        ? t('common.select')
                                        : t('workOrders.priorityNeedEstablishment')
                                }
                                options={toSelectOptions(filteredPriorityOptions)}
                            />
                        )}
                    </Field>

                    <Field label={t('workOrders.responsibleUser')} htmlFor="responsible_user_id" error={errors.responsible_user_id}>
                        {bodyLocked ? (
                            <ReadonlyValue value={optionLabel(userOptions, values.responsible_user_id)} />
                        ) : (
                            <SearchableSelect
                                id="responsible_user_id"
                                value={values.responsible_user_id}
                                invalid={Boolean(errors.responsible_user_id)}
                                onChange={(value) => onChange('responsible_user_id', value)}
                                emptyLabel={t('common.select')}
                                options={toSelectOptions(userOptions)}
                            />
                        )}
                    </Field>

                    <Field label={t('workOrders.requester')} htmlFor="requester_id" error={errors.requester_id}>
                        {bodyLocked ? (
                            <ReadonlyValue value={optionLabel(requesterOptions, values.requester_id)} />
                        ) : (
                            <SearchableSelect
                                id="requester_id"
                                value={values.requester_id}
                                invalid={Boolean(errors.requester_id)}
                                onChange={(value) => onChange('requester_id', value)}
                                emptyLabel={t('common.select')}
                                options={toSelectOptions(requesterOptions)}
                            />
                        )}
                    </Field>

                    <Field label={t('workOrders.reference')} htmlFor="reference" error={errors.reference}>
                        <Input
                            id="reference"
                            value={values.reference}
                            invalid={Boolean(errors.reference)}
                            onChange={(event) => onChange('reference', event.target.value)}
                        />
                    </Field>

                    <Field label={t('workOrders.purchaseOrder')} htmlFor="purchase_order" error={errors.purchase_order}>
                        <Input
                            id="purchase_order"
                            value={values.purchase_order}
                            invalid={Boolean(errors.purchase_order)}
                            onChange={(event) => onChange('purchase_order', event.target.value)}
                        />
                    </Field>

                    <Field label={t('workOrders.createdAt')} htmlFor="created_at">
                        <ReadonlyValue value={formatDateTimeDisplay(createdAt ?? '')} isDate />
                    </Field>

                    <Field label={t('workOrders.receivedAt')} htmlFor="received_at" error={errors.received_at}>
                        {bodyLocked ? (
                            <ReadonlyValue value={formatDateTimeDisplay(values.received_at)} isDate />
                        ) : (
                            <Input
                                id="received_at"
                                type="datetime-local"
                                value={values.received_at}
                                invalid={Boolean(errors.received_at)}
                                onChange={(event) => onChange('received_at', event.target.value)}
                            />
                        )}
                    </Field>

                    <Field label={t('workOrders.interventionAt')} htmlFor="intervention_at" error={errors.intervention_at}>
                        {interventionFieldLocked ? (
                            <ReadonlyValue value={formatDateTimeDisplay(values.intervention_at)} isDate />
                        ) : (
                            <Input
                                id="intervention_at"
                                type="datetime-local"
                                value={values.intervention_at}
                                invalid={Boolean(errors.intervention_at)}
                                onChange={(event) => onChange('intervention_at', event.target.value)}
                            />
                        )}
                    </Field>

                    <Field label={t('workOrders.dueAt')} htmlFor="due_at" error={errors.due_at}>
                        {bodyLocked ? (
                            <ReadonlyValue value={formatDateTimeDisplay(values.due_at)} isDate />
                        ) : (
                            <Input
                                id="due_at"
                                type="datetime-local"
                                value={values.due_at}
                                invalid={Boolean(errors.due_at)}
                                onChange={(event) => onChange('due_at', event.target.value)}
                            />
                        )}
                    </Field>

                    <Field label={t('workOrders.closedAt')} htmlFor="closed_at">
                        <ReadonlyValue value={formatDateTimeDisplay(closedAt ?? '')} isDate />
                    </Field>

                    {!isCreate ? (
                        <Field label={t('workOrders.slaAt')} htmlFor="sla_at" error={errors.sla_at}>
                            {bodyLocked ? (
                                <ReadonlyValue value={formatDateTimeDisplay(values.sla_at)} isDate />
                            ) : (
                                <Input
                                    id="sla_at"
                                    type="datetime-local"
                                    value={values.sla_at}
                                    invalid={Boolean(errors.sla_at)}
                                    onChange={(event) => onChange('sla_at', event.target.value)}
                                />
                            )}
                        </Field>
                    ) : null}
                </div>

                <Field label={t('workOrders.collaborators')} htmlFor="collaborator_ids" error={errors.collaborator_ids}>
                    <MultiSelect
                        id="collaborator_ids"
                        value={values.collaborator_ids}
                        disabled={bodyLocked}
                        onChange={(next) => onChange('collaborator_ids', next)}
                        options={toSelectOptions(userOptions)}
                        placeholder={t('workOrders.collaboratorsPlaceholder')}
                    />
                </Field>
                {actionsSection === 'details' ? formActions : null}
                </Section>
                ) : null}

                {showTasks ? (
                <Section title={t('workOrders.sectionTasks')} description={t('workOrders.sectionTasksDescription')}>
                    <div className="flex items-center justify-between gap-3">
                        <p className="text-sm text-ink-muted">{t('workOrders.tasks')}</p>
                        <Button
                            type="button"
                            variant="secondary"
                            disabled={bodyLocked}
                            onClick={() => onChange('tasks', [...values.tasks, emptyWorkOrderTask()])}
                        >
                            <Plus className="size-3.5" aria-hidden />
                            {t('workOrders.addTask')}
                        </Button>
                    </div>
                    {values.tasks.length === 0 ? (
                        <p className="text-sm text-ink-muted">{t('workOrders.tasksEmpty')}</p>
                    ) : (
                        <div className="space-y-3">
                            {values.tasks.map((task, index) => (
                                <div key={task.id ?? `task-${index}`} className="grid gap-3 rounded-xl border border-line p-3 sm:grid-cols-12">
                                    <Field label={t('common.title')} htmlFor={`task-title-${index}`} className="sm:col-span-4" error={errors[`tasks.${index}.title`]} required>
                                        <Input
                                            id={`task-title-${index}`}
                                            value={task.title}
                                            invalid={Boolean(errors[`tasks.${index}.title`])}
                                            disabled={bodyLocked}
                                            onChange={(event) => {
                                                const next = [...values.tasks];
                                                next[index] = { ...task, title: event.target.value };
                                                onChange('tasks', next);
                                            }}
                                        />
                                    </Field>
                                    <Field label={t('common.description')} htmlFor={`task-description-${index}`} className="sm:col-span-7" error={errors[`tasks.${index}.description`]}>
                                        <textarea
                                            id={`task-description-${index}`}
                                            rows={2}
                                            value={task.description}
                                            disabled={bodyLocked}
                                            onChange={(event) => {
                                                const next = [...values.tasks];
                                                next[index] = { ...task, description: event.target.value };
                                                onChange('tasks', next);
                                            }}
                                            className={cn(
                                                'w-full rounded-lg border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition',
                                                'focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20',
                                                'disabled:cursor-not-allowed disabled:bg-canvas disabled:text-ink-muted disabled:opacity-80',
                                                errors[`tasks.${index}.description`] ? 'border-danger' : 'border-line',
                                            )}
                                        />
                                    </Field>
                                    <div className="flex items-end sm:col-span-1">
                                        <Button
                                            type="button"
                                            variant="danger"
                                            disabled={bodyLocked}
                                            onClick={() => onChange('tasks', values.tasks.filter((_, row) => row !== index))}
                                        >
                                            <Trash2 className="size-3.5" aria-hidden />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                {actionsSection === 'tasks' ? formActions : null}
                </Section>
                ) : null}

                {showTechnicians ? (
                <Section title={t('workOrders.sectionTechnicians')} description={t('workOrders.sectionTechniciansDescription')}>
                    {techniciansLocked && !bodyLocked ? (
                        <p className="rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-ink-muted">
                            {t('workOrders.techniciansLockedInProgress')}
                        </p>
                    ) : null}
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-sm font-semibold text-ink">{t('workOrders.technicians')}</h2>
                        <Button
                            type="button"
                            variant="secondary"
                            disabled={techniciansLocked || !values.establishment_id}
                            onClick={() => {
                                const next = [...values.technicians, emptyWorkOrderTechnician()];
                                onChange('technicians', next);
                                setTechnicianPickerIndex(next.length - 1);
                            }}
                        >
                            <Plus className="size-3.5" aria-hidden />
                            {t('workOrders.addTechnician')}
                        </Button>
                    </div>
                    {!values.establishment_id ? (
                        <p className="text-sm text-ink-muted">{t('workOrders.technicianNeedEstablishment')}</p>
                    ) : null}
                    {values.technicians.length === 0 ? (
                        <p className="text-sm text-ink-muted">{t('workOrders.techniciansEmpty')}</p>
                    ) : (
                        <div className="space-y-3">
                            {values.technicians.map((technician, index) => {
                                const selected = resolvedTechnicianOptions.find(
                                    (option) => String(option.id) === technician.company_relationship_id,
                                );

                                return (
                                <div key={technician.id ?? `tech-${index}`} className="grid gap-3 rounded-xl border border-line p-3 sm:grid-cols-12">
                                    <div className="flex items-end sm:col-span-2">
                                        <Toggle
                                            name={`technicians.${index}.is_selected`}
                                            checked={technician.is_selected}
                                            disabled={techniciansLocked}
                                            onCheckedChange={(checked) => {
                                                const next = [...values.technicians];
                                                next[index] = { ...technician, is_selected: checked };
                                                onChange('technicians', next);
                                            }}
                                            checkedLabel={t('workOrders.selected')}
                                            uncheckedLabel={t('workOrders.notSelected')}
                                        />
                                    </div>
                                    <Field label={t('workOrders.technician')} htmlFor={`tech-${index}`} className="sm:col-span-3" error={errors[`technicians.${index}.company_relationship_id`]}>
                                        <div className="flex gap-2">
                                            <div className="flex min-h-10 min-w-0 flex-1 items-center rounded-lg border border-line bg-canvas/40 px-3 text-sm text-ink">
                                                {selected ? (
                                                    <CompanyOptionLabel name={selected.label} logoUrl={selected.logo_url} size="sm" />
                                                ) : (
                                                    <span className="text-ink-muted">{t('common.select')}</span>
                                                )}
                                            </div>
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                disabled={techniciansLocked || !values.establishment_id}
                                                onClick={() => setTechnicianPickerIndex(index)}
                                            >
                                                <Search className="size-4" aria-hidden />
                                            </Button>
                                        </div>
                                    </Field>
                                    <Field label={t('workOrders.attendanceStatus')} htmlFor={`tech-status-${index}`} className="sm:col-span-2" error={errors[`technicians.${index}.status_id`]}>
                                        <SearchableSelect
                                            id={`tech-status-${index}`}
                                            value={technician.status_id ?? ''}
                                            disabled={techniciansLocked}
                                            onChange={(value) => {
                                                const next = [...values.technicians];
                                                next[index] = { ...technician, status_id: value };
                                                onChange('technicians', next);
                                            }}
                                            emptyLabel={t('common.select')}
                                            options={toSelectOptions(technicianStatusOptions)}
                                        />
                                    </Field>
                                    <Field label={t('workOrders.attendanceType')} htmlFor={`tech-attendance-${index}`} className="sm:col-span-2" error={errors[`technicians.${index}.attendance_confirmation_type_id`]}>
                                        <SearchableSelect
                                            id={`tech-attendance-${index}`}
                                            value={technician.attendance_confirmation_type_id ?? ''}
                                            disabled={techniciansLocked}
                                            onChange={(value) => {
                                                const next = [...values.technicians];
                                                next[index] = { ...technician, attendance_confirmation_type_id: value };
                                                onChange('technicians', next);
                                            }}
                                            emptyLabel={t('common.select')}
                                            options={toSelectOptions(attendanceTypeOptions)}
                                        />
                                    </Field>
                                    {showTechnicianQuotes ? (
                                        <Field label={t('workOrders.quoteNet')} htmlFor={`tech-quote-${index}`} className="sm:col-span-2" error={errors[`technicians.${index}.quote_net_amount`]}>
                                            <Input
                                                id={`tech-quote-${index}`}
                                                type="number"
                                                step="0.01"
                                                value={technician.quote_net_amount}
                                                disabled={techniciansLocked}
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
                                    ) : null}
                                    <div className="flex items-end sm:col-span-1">
                                        <Button
                                            type="button"
                                            variant="danger"
                                            disabled={techniciansLocked}
                                            onClick={() => onChange('technicians', values.technicians.filter((_, row) => row !== index))}
                                        >
                                            <Trash2 className="size-3.5" aria-hidden />
                                        </Button>
                                    </div>
                                </div>
                                );
                            })}
                        </div>
                    )}
                    <TechnicianSearchModal
                        open={technicianPickerIndex !== null}
                        establishmentId={values.establishment_id ? Number(values.establishment_id) : null}
                        establishmentName={selectedEstablishment?.label ?? null}
                        workOrderTypeId={values.work_order_type_id ? Number(values.work_order_type_id) : null}
                        onClose={() => setTechnicianPickerIndex(null)}
                        onSelect={(technician) => {
                            if (technicianPickerIndex === null) {
                                return;
                            }
                            setExtraTechnicianOptions((prev) => {
                                if (prev.some((option) => option.id === technician.id)) {
                                    return prev;
                                }
                                return [...prev, technician];
                            });
                            const next = [...values.technicians];
                            next[technicianPickerIndex] = {
                                ...next[technicianPickerIndex],
                                company_relationship_id: String(technician.id),
                            };
                            onChange('technicians', next);
                            setTechnicianPickerIndex(null);
                        }}
                    />
                {actionsSection === 'technicians' ? formActions : null}
                </Section>
                ) : null}

                {showNotes ? (
                <Section title={t('workOrders.sectionNotes')} description={t('workOrders.sectionNotesDescription')}>
                <Field label={t('workOrders.notes')} htmlFor="notes" error={errors.notes} className="sm:col-span-2">
                    <RichTextEditor
                        id="notes"
                        value={values.notes}
                        invalid={Boolean(errors.notes)}
                        disabled={bodyLocked}
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
                        disabled={bodyLocked}
                        onChange={(html) => onChange('internal_notes', html)}
                    />
                </Field>

                <Toggle
                    name="notes_alert"
                    checked={values.notes_alert}
                    disabled={bodyLocked}
                    onCheckedChange={(checked) => onChange('notes_alert', checked)}
                    checkedLabel={t('workOrders.notesAlert')}
                    uncheckedLabel={t('workOrders.notesAlertOff')}
                />
                <Toggle
                    name="internal_notes_alert"
                    checked={values.internal_notes_alert}
                    disabled={bodyLocked}
                    onCheckedChange={(checked) => onChange('internal_notes_alert', checked)}
                    checkedLabel={t('workOrders.internalNotesAlert')}
                    uncheckedLabel={t('workOrders.internalNotesAlertOff')}
                />
                {actionsSection === 'notes' ? formActions : null}
                </Section>
                ) : null}

                {showLines ? (
                <Section title={t('workOrders.sectionLines')} description={t('workOrders.sectionLinesDescription')}>
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-sm font-semibold text-ink">{t('workOrders.lines')}</h2>
                        <Button
                            type="button"
                            variant="secondary"
                            disabled={bodyLocked}
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
                                            disabled={bodyLocked}
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
                                            disabled={bodyLocked}
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
                                            disabled={bodyLocked}
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
                                            disabled={bodyLocked}
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
                                            disabled={bodyLocked}
                                            onClick={() => onChange('lines', values.lines.filter((_, row) => row !== index))}
                                        >
                                            <Trash2 className="size-3.5" aria-hidden />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                {actionsSection === 'lines' ? formActions : null}
                </Section>
                ) : null}

                {showChecklists ? (
                <Section title={t('workOrders.sectionChecklists')} description={t('workOrders.sectionChecklistsDescription')}>
                    {checklistItems.length === 0 ? (
                        <p className="text-sm text-ink-muted">{t('workOrders.checklistsEmpty')}</p>
                    ) : (
                        <div className="space-y-3">
                            {checklistItems.map((item) => (
                                <div key={item.id} className="flex items-center justify-between rounded-xl border border-line px-4 py-3">
                                    <p className="text-sm text-ink">{item.name}</p>
                                    <Toggle
                                        name={`checklist-${item.id}`}
                                        checked={item.completed}
                                        disabled={bodyLocked || !onChecklistChange}
                                        onCheckedChange={(checked) => onChecklistChange?.(item.id, checked)}
                                        checkedLabel={t('common.yes')}
                                        uncheckedLabel={t('common.no')}
                                    />
                                </div>
                            ))}
                        </div>
                    )}
                {actionsSection === 'checklists' ? formActions : null}
                </Section>
                ) : null}
            </form>
        </FieldHelpScope>
    );
}
